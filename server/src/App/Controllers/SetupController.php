<?php
declare(strict_types=1);

namespace Robert2\API\Controllers;

use DI\Container;
use Robert2\API\Config\Config;
use Robert2\API\Errors\Exception\ValidationException;
use Robert2\API\Http\Request;
use Robert2\API\Models\Category;
use Robert2\API\Models\Enums\Group;
use Robert2\API\Models\User;
use Robert2\API\Services\I18n;
use Robert2\API\Services\View;
use Robert2\Install\Install;
use Slim\Http\Response;

class SetupController extends BaseController
{
    /** @var View */
    private $view;

    /** @var I18n */
    private $i18n;

    /** @var array */
    private $settings;

    public function __construct(Container $container, View $view, I18n $i18n)
    {
        parent::__construct($container);

        $this->view = $view;
        $this->i18n = $i18n;
        $this->settings = $container->get('settings');
    }

    public function index(Request $request, Response $response)
    {
        $currentStep = Install::getStep();
        if ($currentStep === 'end') {
            return $response->withRedirect('/login');
        }

        $stepData = [];
        $error = false;
        $validationErrors = null;

        $lang = $this->i18n->getLanguage();
        $allCurrencies = Install::getAllCurrencies();

        if ($request->isGet()) {
            if ($currentStep === 'welcome') {
                return $this->view->render(
                    $response,
                    'install.twig',
                    $this->_getCheckRequirementsData() + ['lang' => $lang]
                );
            }

            if ($currentStep === 'company') {
                $stepData = $this->settings['companyData'];
            }
        }

        if ($request->isPost()) {
            $installData = $request->getParsedBody();

            $stepSkipped = (
                array_key_exists('skipped', $installData)
                && $installData['skipped'] === 'yes'
            );
            if ($stepSkipped) {
                $installData['skipped'] = true;
            }

            if ($currentStep === 'settings') {
                $installData['currency'] = $allCurrencies[$installData['currency']];
            }

            if ($currentStep === 'company') {
                $installData['logo'] = null;
                ksort($installData);
            }

            try {
                Install::setInstallProgress($currentStep, $installData);

                if ($currentStep === 'company') {
                    $this->_validateCompanyData($installData);
                }

                if ($currentStep === 'database') {
                    $settings = Install::getSettingsFromInstallData();
                    Config::saveCustomConfig($settings);
                    Config::getPDO(); // - Try to connect to DB
                }

                if ($currentStep === 'dbStructure') {
                    Install::migrateDatabase();
                }

                if ($currentStep === 'adminUser') {
                    if ($stepSkipped && !User::where('group', Group::ADMIN)->exists()) {
                        throw new \InvalidArgumentException(
                            "At least one user must exists. Please create an administrator."
                        );
                    }

                    if (!$stepSkipped) {
                        $installData['user']['group'] = Group::ADMIN;
                        User::new($installData['user']);
                    }
                }

                if ($currentStep === 'categories') {
                    $categories = explode(',', $installData['categories']);
                    Category::bulkAdd(array_unique($categories));
                }

                return $response->withRedirect('/install');
            } catch (\Exception $e) {
                Install::setInstallProgress($currentStep);

                $stepData = $installData;
                $error = $e->getMessage();
                if ($e instanceof ValidationException) {
                    $error = "validationErrors";
                    $validationErrors = $e->getValidationErrors();
                }

                if ($currentStep === 'database') {
                    Config::deleteCustomConfig();
                }
            }
        }

        if ($currentStep === 'coreSettings' && empty($stepData['JWTSecret'])) {
            $stepData['JWTSecret'] = md5(uniqid('Loxya', true));
        }

        if ($currentStep === 'settings') {
            $stepData['currencies'] = $allCurrencies;
        }

        if ($currentStep === 'dbStructure') {
            try {
                $stepData = [
                    'migrationStatus' => Install::getMigrationsStatus(),
                    'canProcess' => true,
                ];
            } catch (\Exception $e) {
                $stepData = [
                    'migrationStatus' => [],
                    'errorCode' => $e->getCode(),
                    'error' => $e->getMessage(),
                    'canProcess' => false,
                ];
            }
        }

        if ($currentStep === 'adminUser') {
            $stepData['existingAdmins'] = User::where('group', Group::ADMIN)->get()->toArray();
        }

        return $this->view->render($response, 'install.twig', [
            'lang' => $lang,
            'step' => $currentStep,
            'stepNumber' => array_search($currentStep, Install::INSTALL_STEPS),
            'error' => $error,
            'validationErrors' => $validationErrors,
            'stepData' => $stepData,
            'config' => $this->settings,
        ]);
    }

    public function endInstall(Request $request, Response $response)
    {
        $steps = Install::INSTALL_STEPS;

        $endStep = end($steps);
        $prevStep = prev($steps);

        Install::setInstallProgress($prevStep, ['skipped' => true]);
        Install::setInstallProgress($endStep, []);

        return $response->withRedirect('/login');
    }

    // ------------------------------------------------------
    // -
    // -    Méthodes internes
    // -
    // ------------------------------------------------------

    private function _getCheckRequirementsData(): array
    {
        $phpVersion = PHP_VERSION;
        if (str_contains(PHP_VERSION, '+')) {
            $phpVersion = substr(PHP_VERSION, 0, strpos(PHP_VERSION, '+'));
        }

        $phpversionOK = version_compare(PHP_VERSION, '8.0.0') >= 0;
        $loadedExtensions = get_loaded_extensions();
        $neededExtensions = Install::REQUIRED_EXTENSIONS;
        $missingExtensions = array_diff($neededExtensions, $loadedExtensions);

        return [
            'step' => 'welcome',
            'phpVersion' => $phpVersion,
            'phpVersionOK' => $phpversionOK,
            'missingExtensions' => $missingExtensions,
            'requirementsOK' => $phpversionOK && empty($missingExtensions),
        ];
    }

    private function _validateCompanyData($companyData): void
    {
        $errors = [];
        foreach (['name', 'street', 'zipCode', 'locality'] as $mandatoryField) {
            if (empty($companyData[$mandatoryField])) {
                $errors[$mandatoryField] = "Missing value";
                continue;
            }
        }

        if (empty($errors)) {
            return;
        }

        throw new ValidationException($errors);
    }
}
