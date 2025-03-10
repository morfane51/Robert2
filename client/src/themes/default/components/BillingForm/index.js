import './index.scss';
import Decimal from 'decimal.js';
import config from '@/globals/config';
import Fragment from '@/components/Fragment';
import FormField from '@/themes/default/components/FormField';
import Button from '@/themes/default/components/Button';

// @vue/component
export default {
    name: 'BillingForm',
    props: {
        discountRate: { type: Number, required: true },
        discountTarget: { type: Number, required: true },
        maxRate: { type: Object, default: () => new Decimal(100) },
        maxAmount: { type: Object, default: undefined },
        loading: { type: Boolean, default: false },
        beneficiary: { type: Object, default: undefined },
        saveLabel: {
            type: String,
            default() {
                const { $t: __ } = this;
                return __('save');
            },
        },
    },
    computed: {
        isDiscountable() {
            const { maxRate } = this;
            return maxRate.greaterThan(0);
        },

        targetAmount() {
            const { discountTarget } = this;
            return (new Decimal(discountTarget)).toFixed(2);
        },

        currency() {
            return config.currency.symbol;
        },
    },
    methods: {
        handleChangeRate(givenValue) {
            const rate = new Decimal(givenValue);

            if (rate.isNaN() || !rate.isFinite()) {
                return;
            }

            const value = rate.clampedTo(0, this.maxRate);

            this.$emit('change', { field: 'rate', value });
        },

        handleChangeAmount(givenValue) {
            const amount = new Decimal(givenValue);

            if (amount.isNaN() || !amount.isFinite()) {
                return;
            }

            let max = new Decimal(Infinity);
            if (amount.greaterThan(this.maxAmount ?? Infinity)) {
                max = Decimal.clone(this.maxAmount);
            }

            const value = amount.clampedTo(0, max);

            this.$emit('change', { field: 'amount', value });
        },

        handleSubmit(e) {
            e.preventDefault();
            this.$emit('submit');
        },

        handleCancel() {
            this.$emit('cancel');
        },
    },
    render() {
        const {
            $t: __,
            loading,
            currency,
            saveLabel,
            beneficiary,
            maxRate,
            maxAmount,
            isDiscountable,
            discountRate,
            targetAmount,
            handleSubmit,
            handleCancel,
            handleChangeRate,
            handleChangeAmount,
        } = this;

        const classNames = [
            'Form',
            'BillingForm',
            { 'BillingForm--not-discountable': !isDiscountable },
        ];

        return (
            <form class={classNames} onSubmit={handleSubmit}>
                {!isDiscountable && (
                    <p class="BillingForm__no-discount">{__('no-discount-applicable')}</p>
                )}
                {isDiscountable && (
                    <Fragment>
                        <FormField
                            type="number"
                            label="wanted-discount-rate"
                            class="BillingForm__discount-input"
                            name="discountRate"
                            disabled={loading}
                            value={discountRate}
                            step={0.0001}
                            min={0.0}
                            max={maxRate.toNumber()}
                            addon="%"
                            onInput={handleChangeRate}
                            help={(
                                maxRate.lessThan(100)
                                    ? __('max-discount-rate-help', { rate: maxRate.toFixed(4) })
                                    : undefined
                            )}
                        />
                        <FormField
                            type="number"
                            label="wanted-total-amount"
                            class="BillingForm__discount-target-input"
                            name="discountTarget"
                            disabled={loading}
                            value={targetAmount}
                            step={0.01}
                            min={0}
                            max={maxAmount.toNumber()}
                            addon={currency}
                            onChange={handleChangeAmount}
                        />
                    </Fragment>
                )}
                {!!beneficiary && (
                    <div class="BillingForm__beneficiary">
                        <div class="BillingForm__beneficiary__label">
                            {__('beneficiary')}
                        </div>
                        <div class="BillingForm__beneficiary__name">
                            {beneficiary.full_name}
                        </div>
                    </div>
                )}
                <div class="BillingForm__save">
                    <Button htmlType="submit" type="primary" loading={loading}>
                        {saveLabel}
                    </Button>
                    <Button onClick={handleCancel} disabled={loading}>
                        {__('cancel')}
                    </Button>
                </div>
            </form>
        );
    },
};
