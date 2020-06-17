<?php
declare(strict_types=1);

namespace Robert2\API\Models\Traits;

use Robert2\Lib\Pdf\Pdf;
use Robert2\API\I18n\I18n;
use Robert2\API\Config\Config;
use Robert2\API\Errors\NotFoundException;

trait WithPdf
{
    protected $pdfTemplate = null;

    // ------------------------------------------------------
    // -
    // -    Getters
    // -
    // ------------------------------------------------------

    public function getPdfName(int $id): string
    {
        $model = $this->withTrashed()->find($id);
        if (!$model) {
            throw new NotFoundException(sprintf('%s not found.', $this->_modelName));
        }

        $company = Config::getSettings('companyData');

        $i18n = new I18n(Config::getSettings('defaultLang'));
        $fileName = sprintf(
            '%s-%s-%s-%s.pdf',
            $i18n->translate($this->_modelName),
            slugify($company['name']),
            $model->number,
            slugify($model->Beneficiary->full_name)
        );
        if (isTestMode()) {
            $fileName = sprintf('TEST-%s', $fileName);
        }

        return $fileName;
    }

    // ------------------------------------------------------
    // -
    // -    Internal Methods
    // -
    // ------------------------------------------------------

    protected function _savePdf(int $id, array $data): bool
    {
        if (empty($this->pdfTemplate)) {
            throw new \RuntimeException("Missing model's PDF template name");
        }

        $fileName = $this->getPdfName($id);
        $filePath = DATA_FOLDER . DS . $this->table . DS . $fileName;

        return Pdf::createFromTemplate($this->pdfTemplate, $data, $filePath);
    }

    public function _unlinkPdf(int $id): void
    {
        if (!$id) {
            throw new \RuntimeException("Missing model's ID to delete PDF file.");
        }

        $fileName = $this->getPdfName($id);
        $filePath = DATA_FOLDER . DS . $this->table . DS . $fileName;
        if (!file_exists($filePath)) {
            return;
        }

        @unlink($filePath);
    }
}
