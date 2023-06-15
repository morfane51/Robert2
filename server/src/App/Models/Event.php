<?php
declare(strict_types=1);

namespace Robert2\API\Models;

use Adbar\Dot as DotArray;
use Brick\Math\BigDecimal as Decimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as CoreCollection;
use Respect\Validation\Validator as V;
use Robert2\API\Config\Config;
use Robert2\API\Contracts\Bookable;
use Robert2\API\Contracts\PeriodInterface;
use Robert2\API\Contracts\Serializable;
use Robert2\API\Errors\Exception\ValidationException;
use Robert2\API\Models\Traits\Cache;
use Robert2\API\Models\Traits\Pdfable;
use Robert2\API\Models\Traits\Serializer;
use Robert2\API\Models\Traits\SoftDeletable;
use Robert2\API\Models\Traits\TransientAttributes;
use Robert2\API\Services\I18n;
use Robert2\Support\Collections\MaterialsCollection;
use Robert2\Support\Str;
use Symfony\Contracts\Cache\ItemInterface as CacheItemInterface;

/**
 * Événement.
 *
 * @property-read ?int $id
 * @property int|null $user_id
 * @property-read User|null $user
 * @property string $title
 * @property string|null $reference
 * @property string|null $description
 * @property string|null $location
 * @property string $start_date
 * @property string $end_date
 * @property string|null $color
 * @property-read int $duration
 * @property-read Decimal|null $degressive_rate
 * @property Decimal|null $discount_rate
 * @property-read Decimal|null $vat_rate
 * @property-read Decimal|null $daily_total_without_discount
 * @property-read Decimal|null $daily_total_discountable
 * @property-read Decimal|null $daily_total_discount
 * @property-read Decimal|null $daily_total_without_taxes
 * @property-read Decimal|null $daily_total_taxes
 * @property-read Decimal|null $daily_total_with_taxes
 * @property-read Decimal|null $total_without_taxes
 * @property-read Decimal|null $total_taxes
 * @property-read Decimal|null $total_with_taxes
 * @property-read Decimal $total_replacement
 * @property-read string $currency
 * @property bool $is_confirmed
 * @property bool $is_archived
 * @property bool $is_billable
 * @property bool $is_return_inventory_started
 * @property bool $is_return_inventory_done
 * @property-read bool|null $has_missing_materials
 * @property-read bool|null $has_not_returned_materials
 * @property-read int[] $parks
 * @property string|null $note
 * @property-read array $totalisableAttributes
 * @property-read Carbon $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Carbon|null $deleted_at
 *
 * @property-read Collection|EventTechnician[] $technicians
 * @property-read Collection|Beneficiary[] $beneficiaries
 * @property-read Collection|Material[] $materials
 * @property-read Collection|Estimate[] $estimates
 * @property-read Collection|Invoice[] $invoices
 * @property-read Collection|Document[] $documents
 *
 * @method static Builder|static inPeriod(PeriodInterface $period)
 * @method static Builder|static inPeriod(string|Carbon $start, string|Carbon|null $end)
 * @method static Builder|static inPeriod(string|Carbon|PeriodInterface $start, string|Carbon|null $end = null)
 * @method static Builder|static search(string $term)
 * @method static Builder|static notReturned(Carbon|PeriodInterface $dateOrPeriod)
 */
final class Event extends BaseModel implements Serializable, PeriodInterface, Bookable
{
    use Serializer;
    use SoftDeletable;
    use Pdfable;
    use Cache;
    use TransientAttributes;

    /** L'identifiant unique du modèle. */
    public const TYPE = 'event';

    // - Types de sérialisation.
    public const SERIALIZE_DEFAULT = 'default';
    public const SERIALIZE_SUMMARY = 'summary';
    public const SERIALIZE_BOOKING_DEFAULT = 'booking-default';
    public const SERIALIZE_BOOKING_SUMMARY = 'booking-summary';
    public const SERIALIZE_DETAILS = 'details';

    /**
     * Cette variable peut être utilisée pour mettre en cache les bookables
     * qui se déroulent en même temps que celui de l'instance courante.
     *
     * @var Collection|(Event)[]
     */
    public ?CoreCollection $__cachedConcurrentBookables = null;

    protected const PDF_TEMPLATE = 'event-summary-default';

    protected $orderField = 'start_date';

    protected $allowedSearchFields = ['title', 'start_date', 'end_date', 'location'];
    protected $searchField = 'title';

    protected $attributes = [
        'color' => null,
        'is_archived' => false,
        'is_return_inventory_done' => false,
        'note' => null,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->validation = [
            'user_id' => V::optional(V::numericVal()),
            'title' => V::notEmpty()->length(2, 191),
            'reference' => V::custom([$this, 'checkReference']),
            'start_date' => V::notEmpty()->dateTime(),
            'end_date' => V::custom([$this, 'checkEndDate']),
            'color' => V::optional(V::custom([$this, 'checkColor'])),
            'is_confirmed' => V::notOptional()->boolType(),
            'is_archived' => V::custom([$this, 'checkIsArchived']),
            'is_billable' => V::optional(V::boolType()),
            'is_return_inventory_done' => V::optional(V::boolType()),
        ];
    }

    // ------------------------------------------------------
    // -
    // -    Validation
    // -
    // ------------------------------------------------------

    public function checkReference($value)
    {
        V::create()
            ->nullable(V::alnum('.,-/_ ')->length(1, 64))
            ->check($value);

        if (!$value) {
            return true;
        }

        $query = static::where('reference', $value);
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        if ($query->withTrashed()->exists()) {
            return 'reference-already-in-use';
        }

        return true;
    }

    public function checkEndDate($value)
    {
        $dateChecker = V::notEmpty()->dateTime();
        if (!$dateChecker->validate($value)) {
            return 'invalid-date';
        }

        if (!$dateChecker->validate($this->start_date)) {
            return true;
        }

        $startDate = new Carbon($this->start_date);
        $endDate = new Carbon($this->end_date);

        return $startDate < $endDate ?: 'end-date-must-be-later';
    }

    public function checkColor($value)
    {
        $colorChecker = V::regex('/^#?[0-9a-f]{6}$/i');
        return $colorChecker->validate($value) ?: 'invalid-hexadecimal-color';
    }

    public function checkIsArchived($value)
    {
        V::boolType()->check($value);

        if (!$value) {
            return true;
        }

        $boolChecker = V::notOptional()->boolType();
        if (!$boolChecker->validate($value)) {
            return false;
        }

        $dateChecker = V::notEmpty()->dateTime();
        if (!$dateChecker->validate($this->getAttributeFromArray('end_date'))) {
            return false;
        }

        $isPastAndInventoryDone = (
            (new Carbon($this->getAttributeFromArray('end_date'))) < Carbon::now()
            && $this->is_return_inventory_done
        );

        return $isPastAndInventoryDone ?: 'event-cannot-be-archived';
    }

    // ------------------------------------------------------
    // -
    // -    Relations
    // -
    // ------------------------------------------------------

    // TODO: Renommer ça en `author` (et la clé dans la table aussi (`author_id`))
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function technicians()
    {
        return $this->hasMany(EventTechnician::class, 'event_id')
            ->orderBy('start_time');
    }

    public function beneficiaries()
    {
        return $this->belongsToMany(Beneficiary::class, 'event_beneficiaries')
            ->orderByPivot('id');
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'event_materials')
            ->using(EventMaterial::class)
            ->withPivot(
                'id',
                'quantity',
                'quantity_returned',
                'quantity_returned_broken'
            )
            ->orderByPivot('id');
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'booking')
            ->orderBy('date', 'desc');
    }

    public function estimates()
    {
        return $this->morphMany(Estimate::class, 'booking')
            ->orderBy('date', 'desc');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'entity')
            ->orderBy('name', 'asc');
    }

    // ------------------------------------------------------
    // -
    // -    Mutators
    // -
    // ------------------------------------------------------

    protected $casts = [
        'user_id' => 'integer',
        'reference' => 'string',
        'title' => 'string',
        'description' => 'string',
        'start_date' => 'string',
        'end_date' => 'string',
        'color' => 'string',
        'is_confirmed' => 'boolean',
        'is_archived' => 'boolean',
        'location' => 'string',
        'is_billable' => 'boolean',
        'is_return_inventory_done' => 'boolean',
        'note' => 'string',
    ];

    public function getBeneficiariesAttribute()
    {
        return $this->getRelationValue('beneficiaries')
            ->sortBy('last_name')
            ->values();
    }

    public function getTechniciansAttribute()
    {
        return $this->getRelationValue('technicians');
    }

    public function getUserAttribute()
    {
        return $this->getRelationValue('user');
    }

    public function getMaterialsAttribute()
    {
        return $this->getRelationValue('materials');
    }

    public function getEstimatesAttribute()
    {
        return $this->getRelationValue('estimates');
    }

    public function getInvoicesAttribute()
    {
        return $this->getRelationValue('invoices');
    }

    public function getDurationAttribute(): int
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        if (!$startDate || !$endDate || $endDate < $startDate) {
            throw new \RuntimeException('Wrong reservation dates.');
        }

        $diff = $startDate->diff($endDate);
        return (int) $diff->format('%a') + 1;
    }

    public function getDegressiveRateAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        $result = null;
        $jsFunction = Config::getSettings('degressiveRateFunction');
        if (!empty($jsFunction) && str_contains($jsFunction, 'daysCount')) {
            $function = preg_replace('/daysCount/', (string) $this->duration, $jsFunction);
            eval(sprintf('$result = %s;', $function)); // phpcs:ignore Squiz.PHP.Eval
        }

        return Decimal::of($result && is_numeric($result) ? $result : $this->duration)
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public function getDiscountRateAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }
        return $this->getTransientAttribute('discount_rate', Decimal::zero());
    }

    public function getVatRateAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return Decimal::of(Config::getSettings('companyData')['vatRate'] ?: 0)
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    //
    // - Daily totals.
    //

    public function getDailyTotalWithoutDiscountAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->materials->pluck('pivot')
            ->reduce(
                fn (Decimal $currentTotal, EventMaterial $material) => (
                    $currentTotal->plus($material->total_price)
                ),
                Decimal::zero()
            )
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public function getDailyTotalDiscountableAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->materials->pluck('pivot')
            ->reduce(
                fn (Decimal $currentTotal, EventMaterial $material) => (
                    $material->is_discountable
                        ? $currentTotal->plus($material->total_price)
                        : $currentTotal
                ),
                Decimal::zero()
            )
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public function getDailyTotalDiscountAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->daily_total_discountable
            ->multipliedBy($this->discount_rate->dividedBy(100, 6))
            // @see https://www.ibm.com/docs/en/order-management-sw/9.2.1?topic=rounding-price
            // @see https://wiki.dolibarr.org/index.php?title=VAT_setup,_calculation_and_rounding_rules
            ->toScale(2, RoundingMode::HALF_UP);
    }

    public function getDailyTotalWithoutTaxesAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->daily_total_without_discount
            ->minus($this->daily_total_discount)
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public function getDailyTotalTaxesAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->daily_total_without_taxes
            ->multipliedBy($this->vat_rate->dividedBy(100, 4))
            // @see https://www.ibm.com/docs/en/order-management-sw/9.2.1?topic=rounding-price
            // @see https://wiki.dolibarr.org/index.php?title=VAT_setup,_calculation_and_rounding_rules
            ->toScale(2, RoundingMode::HALF_UP);
    }

    public function getDailyTotalWithTaxesAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->daily_total_without_taxes
            ->plus($this->daily_total_taxes)
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    //
    // - Totals.
    //

    public function getTotalWithoutTaxesAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->daily_total_without_taxes
            ->multipliedBy($this->degressive_rate)
            // @see https://www.ibm.com/docs/en/order-management-sw/9.2.1?topic=rounding-price
            // @see https://wiki.dolibarr.org/index.php?title=VAT_setup,_calculation_and_rounding_rules
            ->toScale(2, RoundingMode::HALF_UP);
    }

    public function getTotalTaxesAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->total_without_taxes
            ->multipliedBy($this->vat_rate->dividedBy(100, 4))
            // @see https://www.ibm.com/docs/en/order-management-sw/9.2.1?topic=rounding-price
            // @see https://wiki.dolibarr.org/index.php?title=VAT_setup,_calculation_and_rounding_rules
            ->toScale(2, RoundingMode::HALF_UP);
    }

    public function getTotalWithTaxesAttribute(): ?Decimal
    {
        if (!$this->is_billable) {
            return null;
        }

        return $this->total_without_taxes
            ->plus($this->total_taxes)
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public function getTotalReplacementAttribute(): ?Decimal
    {
        return $this->materials->pluck('pivot')
            ->reduce(
                fn (Decimal $currentTotal, EventMaterial $material) => (
                    $currentTotal->plus($material->total_replacement_price)
                ),
                Decimal::zero()
            )
            ->toScale(2, RoundingMode::UNNECESSARY);
    }

    public function getCurrencyAttribute(): string
    {
        return Config::getSettings('currency')['iso'];
    }

    public function getHasMissingMaterialsAttribute()
    {
        if (!$this->exists || $this->is_archived) {
            return null;
        }

        // - Si l'événement est passé, la disponibilité du matériel n'est pas calculée.
        $endDate = $this->getEndDate();
        if ($endDate < Carbon::today()) {
            return null;
        }

        return $this->cacheGet(
            'has_missing_materials',
            function (?CacheItemInterface $cacheItem) {
                if ($cacheItem) {
                    $cacheItem->expiresAfter(new \DateInterval('P1D'));
                }
                return $this->missingMaterials()->isNotEmpty();
            }
        );
    }

    public function getIsReturnInventoryStartedAttribute(): bool
    {
        // - Si l'inventaire de retour est terminé, c'est qu'il a forcément commencé.
        if ($this->is_return_inventory_done) {
            return true;
        }

        // - Si l'événement n'a pas encore commencé, l'inventaire de retour ne peut
        //   pas avoir commencé non plus.
        $startDate = $this->getStartDate();
        if ($startDate >= Carbon::tomorrow()) {
            return false;
        }

        // - S'il existe au moins une quantité retournée non nulle,
        //   c'est que l'inventaire de retour a été sauvegardé au moins une fois.
        return $this->materials->whereNotNull('pivot.quantity_returned')->isNotEmpty();
    }

    public function getHasNotReturnedMaterialsAttribute()
    {
        if (!$this->exists || $this->is_archived) {
            return null;
        }

        $endDate = $this->getEndDate();
        if ($endDate >= Carbon::today() || !$this->is_return_inventory_done) {
            return null;
        }

        return $this->cacheGet(
            'has_not_returned_materials',
            function (?CacheItemInterface $cacheItem) {
                if ($cacheItem) {
                    $cacheItem->expiresAfter(new \DateInterval('P1D'));
                }

                if ($this->materials->isEmpty()) {
                    return false;
                }

                foreach ($this->materials as $material) {
                    $missing = $material->pivot->quantity - $material->pivot->quantity_returned;
                    if ($missing > 0) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    public function getParksAttribute()
    {
        $parkIds = $this->materials->reduce(
            function (array $parkIds, Material $material) {
                $parkIds[] = $material->park_id;
                return $parkIds;
            },
            [],
        );
        return array_values(array_unique($parkIds));
    }

    public function getTotalisableAttributesAttribute(): array
    {
        return $this->materials->reduce(
            function ($totals, $material) {
                $quantity = $material->pivot->quantity;

                foreach ($material->attributes as $attribute) {
                    if (!$attribute->is_totalisable) {
                        continue;
                    }

                    $currentTotal = $quantity * $attribute->value;

                    if (!array_key_exists($attribute->id, $totals)) {
                        $totals[$attribute->id] = $attribute->fresh();
                    }

                    $previousTotal = $totals[$attribute->id]->value ?? 0;
                    $totals[$attribute->id]->value = $previousTotal + $currentTotal;
                }

                return $totals;
            },
            [],
        );
    }

    // ------------------------------------------------------
    // -
    // -    Getters
    // -
    // ------------------------------------------------------

    public function getStartDate(): Carbon
    {
        return (new Carbon($this->start_date))
            ->setTime(0, 0, 0, 0);
    }

    public function getEndDate(): Carbon
    {
        return (new Carbon($this->end_date))
            ->setTime(23, 59, 59, 59);
    }

    // ------------------------------------------------------
    // -
    // -    Setters
    // -
    // ------------------------------------------------------

    protected $fillable = [
        'user_id',
        'reference',
        'title',
        'description',
        'start_date',
        'end_date',
        'color',
        'is_confirmed',
        'is_archived',
        'location',
        'is_billable',
        'is_return_inventory_done',
        'note',
    ];

    public function setReferenceAttribute($value)
    {
        $value = is_string($value) ? trim($value) : $value;
        $this->attributes['reference'] = $value;
    }

    public function setNoteAttribute($value)
    {
        $value = is_string($value) ? trim($value) : $value;
        $this->attributes['note'] = $value === '' ? null : $value;
    }

    public function setDiscountRateAttribute(Decimal $value)
    {
        if (!$this->is_billable) {
            throw new \LogicException("Unable to set a discount rate on a non-billable event.");
        }
        $this->setTransientAttribute('discount_rate', $value);
    }

    // ------------------------------------------------------
    // -
    // -    Méthodes liées à une "entity"
    // -
    // ------------------------------------------------------

    public function missingMaterials(): Collection
    {
        $materials = Material::allWithAvailabilities($this->materials, $this, true);
        return $materials
            ->map(function ($material) {
                $availableQuantity = $material->available_quantity;

                $missingQuantity = $material->pivot->quantity - $availableQuantity;
                $missingQuantity = min($missingQuantity, $material->pivot->quantity);
                $material->pivot->quantity_missing = $missingQuantity;

                return $material;
            })
            ->filter(fn($material) => $material->pivot->quantity_missing > 0)
            ->sortBy('name')
            ->values();
    }

    public function syncBeneficiaries(array $beneficiariesIds)
    {
        $this->beneficiaries()->sync($beneficiariesIds);
        $this->refresh();
    }

    public function syncTechnicians(array $techniciansData)
    {
        $errors = [];
        $technicians = [];
        foreach ($techniciansData as $technicianData) {
            try {
                $eventTechnician = new EventTechnician([
                    'event_id' => $this->id,
                    'technician_id' => $technicianData['id'],
                    'start_time' => $technicianData['start_time'],
                    'end_time' => $technicianData['end_time'],
                    'position' => $technicianData['position'],
                ]);
                $technicians[] = $eventTechnician->withoutAlreadyBusyChecks()->validate();
            } catch (ValidationException $e) {
                $errors[$technicianData['id']] = $e->getValidationErrors();
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // FIXME: Transaction.
        EventTechnician::flushForEvent($this->id);
        $this->technicians()->saveMany($technicians);
        $this->refresh();
    }

    /**
     * @inheritdoc
     */
    public function syncMaterials(array $materialsData): void
    {
        $materials = [];
        foreach ($materialsData as $materialData) {
            if ((int) $materialData['quantity'] <= 0) {
                continue;
            }

            try {
                $material = Material::findOrFail($materialData['id']);
                $materials[$materialData['id']] = [
                    'quantity' => $materialData['quantity'],
                ];
            } catch (ModelNotFoundException $e) {
                throw new \InvalidArgumentException(
                    "One or more materials added to the event do not exist."
                );
            }
        }

        $this->materials()->sync($materials);
        $this->refresh();
    }

    public function duplicate(array $newEventData, ?User $author = null)
    {
        $newEvent = new self([
            'user_id' => $author?->id,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $newEventData['start_date'] ?? null,
            'end_date' => $newEventData['end_date'] ?? null,
            'color' => $this->color,
            'is_confirmed' => false,
            'is_archived' => false,
            'location' => $this->location,
            'is_billable' => $this->is_billable,
            'is_return_inventory_done' => false,
            'note' => $this->note,
        ]);
        $newEvent->validate();

        $beneficiaries = $this->beneficiaries->pluck('id')->all();
        $technicians = EventTechnician::getForNewDates(
            $this->technicians,
            new \DateTime($this->start_date),
            $newEventData
        );

        $materials = $this->materials
            ->map(fn($material) => [
                'id' => $material->id,
                'quantity' => $material->pivot->quantity,
            ])
            ->all();

        $newEvent->save();
        $newEvent->syncBeneficiaries($beneficiaries);
        $newEvent->syncTechnicians($technicians);
        $newEvent->syncMaterials($materials);

        return $newEvent->refresh();
    }

    // ------------------------------------------------------
    // -
    // -    Query Scopes
    // -
    // ------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function scopeInPeriod(Builder $query, $start, $end = null): Builder
    {
        if ($start instanceof PeriodInterface) {
            $end = $start->getEndDate();
            $start = $start->getStartDate();
        }

        // - Si pas de date de fin: Période d'une journée.
        $end = $end ?? $start;

        if (!$start instanceof Carbon) {
            $start = new Carbon($start);
        }
        if (!$end instanceof Carbon) {
            $end = new Carbon($end);
        }

        return $query
            ->orderBy('start_date', 'asc')
            ->where([
                ['end_date', '>=', $start->format('Y-m-d 00:00:00')],
                ['start_date', '<=', $end->format('Y-m-d 23:59:59')],
            ]);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $trimmedTerm = trim($term);
        if (strlen($trimmedTerm) < 2) {
            throw new \InvalidArgumentException("The term must contain more than two characters.");
        }

        $safeTerm = sprintf('%%%s%%', addcslashes($trimmedTerm, '%_'));
        return $query->where('title', 'LIKE', $safeTerm);
    }

    /**
     * @param Builder $query
     * @param Carbon|PeriodInterface $dateOrPeriod
     *
     * @return Builder
     */
    public function scopeNotReturned(Builder $query, Carbon|PeriodInterface $dateOrPeriod): Builder
    {
        $endDate = $dateOrPeriod;
        $minDate = null;
        if ($dateOrPeriod instanceof PeriodInterface) {
            $endDate = $dateOrPeriod->getEndDate();
            $minDate = $dateOrPeriod->getStartDate();
        }

        $query = $query->where('is_return_inventory_done', false)
            ->where('is_archived', false)
            ->whereHas('materials', function (Builder $subQuery) {
                $subQuery->whereRaw('`quantity` > COALESCE(`quantity_returned`, 0)');
            });

        if ($minDate) {
            $query = $query->whereDate('end_date', '<=', $endDate)
                ->whereDate('end_date', '>', $minDate);
        } else {
            $query = $query->whereDate('end_date', '=', $endDate);
        }

        return $query;
    }

    // ------------------------------------------------------
    // -
    // -    PDF Related
    // -
    // ------------------------------------------------------

    protected function getPdfName(I18n $i18n): string
    {
        $company = Config::getSettings('companyData');
        return Str::slugify(implode('-', [
            $i18n->translate('release-sheet'),
            $company['name'],
            $this->title ?: $this->id,
        ]));
    }

    protected function getPdfData(): array
    {
        $displayMode = Setting::getWithKey('eventSummary.materialDisplayMode');
        $materials = new MaterialsCollection($this->materials);
        switch ($displayMode) {
            case 'categories':
                $materials = $materials->byCategories();
                break;
            case 'sub-categories':
                $materials = $materials->bySubCategories();
                break;
            case 'parks':
                $materials = $materials->byParks();
                break;
            default:
                break;
        }

        $technicians = [];
        foreach ($this->technicians as $eventTechnician) {
            $technician = $eventTechnician->technician;

            if (!array_key_exists($technician->id, $technicians)) {
                $technicians[$technician->id] = [
                    'id' => $technician->id,
                    'name' => $technician->full_name,
                    'phone' => $technician->phone,
                    'periods' => [],
                ];
            }

            $startTime = Carbon::createFromFormat('Y-m-d H:i:s', $eventTechnician->start_time, 'UTC');
            $endTime = Carbon::createFromFormat('Y-m-d H:i:s', $eventTechnician->end_time, 'UTC');

            $technicians[$technician->id]['periods'][] = [
                'from' => $startTime,
                'to' => $endTime,
                'position' => $eventTechnician->position,
            ];
        }

        $rawData = (clone $this)
            ->append(['materials', 'beneficiaries', 'technicians'])
            ->toArray();

        return [
            'date' => CarbonImmutable::now(),
            'event' => $rawData,
            'beneficiaries' => $this->beneficiaries,
            'company' => Config::getSettings('companyData'),
            'currency' => Config::getSettings('currency')['iso'],
            'sortedMaterials' => $materials,
            'materialDisplayMode' => $displayMode,
            'replacementAmount' => $this->total_replacement,
            'technicians' => array_values($technicians),
            'totalisableAttributes' => $this->totalisableAttributes,
            'customText' => Setting::getWithKey('eventSummary.customText'),
            'showLegalNumbers' => Setting::getWithKey('eventSummary.showLegalNumbers'),
        ];
    }

    // ------------------------------------------------------
    // -
    // -    Méthodes de "repository"
    // -
    // ------------------------------------------------------

    // TODO => Static.
    public function getAll(bool $withDeleted = false): Builder
    {
        $builder = static::inPeriod(
            'first day of January this year',
            'last day of December this year',
        );

        if ($withDeleted) {
            $builder = $builder->onlyTrashed();
        }

        return $builder;
    }

    public static function staticEdit($id = null, array $data = []): BaseModel
    {
        if ($id && !static::staticExists($id)) {
            throw (new ModelNotFoundException)
                ->setModel(self::class, $id);
        }

        $event = static::firstOrNew(compact('id'));

        $originalStartDate = $event->getOriginal('start_date');
        $originalEndDate = $event->getOriginal('end_date');

        $event->fill($data)->save();

        if (isset($data['beneficiaries'])) {
            if (!is_array($data['beneficiaries'])) {
                throw new \InvalidArgumentException("Key 'beneficiaries' must be an array.");
            }
            $event->syncBeneficiaries($data['beneficiaries']);
        }

        $technicians = null;
        if (isset($data['technicians'])) {
            if (!is_array($data['technicians'])) {
                throw new \InvalidArgumentException("Key 'technicians' must be an array.");
            }
            $technicians = $data['technicians'];
        } elseif (
            !empty($originalStartDate) &&
            (
                $originalStartDate !== $event->start_date ||
                $originalEndDate !== $event->end_date
            )
        ) {
            $technicians = EventTechnician::getForNewDates(
                $event->technicians,
                new \DateTime($originalStartDate),
                ['start_date' => $event->start_date, 'end_date' => $event->end_date]
            );
        }
        if ($technicians) {
            $event->syncTechnicians($technicians);
        }

        if (isset($data['materials'])) {
            if (!is_array($data['materials'])) {
                throw new \InvalidArgumentException("Key 'materials' must be an array.");
            }
            $event->syncMaterials($data['materials']);
        }

        return $event->refresh();
    }

    // ------------------------------------------------------
    // -
    // -    Serialization
    // -
    // ------------------------------------------------------

    public function serialize(string $format = self::SERIALIZE_DEFAULT): array
    {
        /** @var Event $event */
        $event = tap(clone $this, function (Event $event) use ($format) {
            if ($format === self::SERIALIZE_BOOKING_SUMMARY) {
                $event->append([
                    'technicians',
                    'beneficiaries',
                    'has_missing_materials',
                    'has_not_returned_materials',
                    'parks',
                ]);
            }

            if (in_array($format, [self::SERIALIZE_DETAILS, self::SERIALIZE_BOOKING_DEFAULT], true)) {
                $event->append([
                    'user',
                    'beneficiaries',
                    'technicians',
                    'duration',
                    'total_replacement',
                    'currency',
                    'materials',
                    'has_missing_materials',
                    'is_return_inventory_started',
                    'has_not_returned_materials',
                ]);

                if ($event->is_billable) {
                    $event->append([
                        'degressive_rate',
                        'vat_rate',
                        'discount_rate',
                        'daily_total_without_discount',
                        'daily_total_discountable',
                        'daily_total_discount',
                        'daily_total_without_taxes',
                        'daily_total_taxes',
                        'daily_total_with_taxes',
                        'total_without_taxes',
                        'total_taxes',
                        'total_with_taxes',
                        'estimates',
                        'invoices',
                    ]);
                }
            }
        });

        $data = new DotArray($event->attributesForSerialization());

        if ($format === self::SERIALIZE_BOOKING_SUMMARY) {
            $data->delete(['note']);
        }

        if ($format === self::SERIALIZE_SUMMARY) {
            $data->delete([
                'reference',
                'description',
                'color',
                'is_confirmed',
                'is_billable',
                'is_archived',
                'is_return_inventory_done',
                'user_id',
                'note',
                'created_at',
                'updated_at',
            ]);
        }

        // - On ajoute manuellement le matériel car on veut les données du pivot avec.
        if ($event->hasAppended('materials')) {
            $data['materials'] = $event->materials
                ->map(fn($material) => (
                    array_merge($material->serialize(), [
                        'pivot' => $material->pivot->attributesToArray(),
                    ])
                ))
                ->all();
        }

        return $data
            ->delete(['deleted_at'])
            ->all();
    }
}
