<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\Mbo\Addons\Module;

use Doctrine\Common\Cache\CacheProvider;
use Exception;
use Module as LegacyModule;
use PrestaShop\Module\Mbo\Addons\AddonsCollection;
use PrestaShop\Module\Mbo\Addons\ListFilter;
use PrestaShop\Module\Mbo\Addons\ListFilterOrigin;
use PrestaShop\Module\Mbo\Addons\ListFilterStatus;
use PrestaShop\Module\Mbo\Addons\ListFilterType;
use PrestaShop\PrestaShop\Adapter\Module\Module;
use PrestaShop\PrestaShop\Adapter\Module\ModuleDataUpdater;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\TranslatorInterface;

class ModuleRepository implements ModuleRepositoryInterface
{
    public const NATIVE_AUTHOR = 'PrestaShop';

    public const PARTNER_AUTHOR = 'PrestaShop Partners';

    /**
     * @var AdminModuleDataProvider
     */
    private $adminModuleProvider;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ModuleDataProvider
     */
    private $moduleProvider;

    /**
     * @var ModuleDataUpdater
     */
    private $moduleUpdater;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * Path to the module directory, coming from Confiuration class.
     *
     * @var string
     */
    private $modulePath;

    /**
     * Key of the cache content.
     *
     * @var string
     */
    private $cacheFilePath;

    /**
     * Contains data from cache file about modules on disk.
     *
     * @var array
     */
    private $cache = [];

    /**
     * Optionnal Doctrine cache provider.
     *
     * @var CacheProvider|null
     */
    private $cacheProvider;

    /**
     * Keep loaded modules in cache.
     *
     * @var DoctrineProvider
     */
    private $loadedModules;

    public function __construct(
        AdminModuleDataProvider $adminModulesProvider,
        ModuleDataProvider $modulesProvider,
        ModuleDataUpdater $modulesUpdater,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        $modulePath,
        CacheProvider $cacheProvider = null
    ) {
        $this->adminModuleProvider = $adminModulesProvider;
        $this->logger = $logger;
        $this->moduleProvider = $modulesProvider;
        $this->moduleUpdater = $modulesUpdater;
        $this->translator = $translator;
        $this->finder = new Finder();
        $this->modulePath = $modulePath;

        list($isoLang) = explode('-', $translator->getLocale());

        // Cache related variables
        $this->cacheFilePath = $isoLang . '_local_modules';
        $this->cacheProvider = $cacheProvider;
        $this->loadedModules = new DoctrineProvider(new ArrayAdapter());

        if ($this->cacheProvider && $this->cacheProvider->contains($this->cacheFilePath)) {
            $this->cache = $this->cacheProvider->fetch($this->cacheFilePath);
        }
    }

    public function __destruct()
    {
        if ($this->cacheProvider) {
            $this->cacheProvider->save($this->cacheFilePath, $this->cache);
        }
    }

    public function clearCache()
    {
        if ($this->cacheProvider) {
            $this->cacheProvider->delete($this->cacheFilePath);
        }
        $this->cache = [];
    }

    /**
     * Get the **Legacy** Module object from its name.
     *
     * @param string $name The technical module name to instanciate
     *
     * @return \Module|null Instance of legacy Module, if valid
     */
    public function getInstanceByName($name)
    {
        // Return legacy instance !
        return $this->getModule($name)->getInstance();
    }

    /**
     * @param ListFilter $filter
     * @param bool $skip_main_class_attributes
     *
     * @return array<Module> retrieve a list of addons, regarding the $filter used
     */
    public function getFilteredList(ListFilter $filter, $skip_main_class_attributes = false)
    {
        /** @var Module[] $modules */
        $modules = $this->getList();

        foreach ($modules as $key => &$module) {
            // Part One : Removing addons not related to the selected product type
            if ($filter->type != ListFilterType::ALL) {
                if ($module->attributes->get('productType') == 'module') {
                    $productType = ListFilterType::MODULE;
                }
                if ($module->attributes->get('productType') == 'service') {
                    $productType = ListFilterType::SERVICE;
                }
                if (!isset($productType) || $productType & ~$filter->type) {
                    unset($modules[$key]);

                    continue;
                }
            }

            // Part Two : Remove module not installed if specified
            if ($filter->status != ListFilterStatus::ALL) {
                if ($module->database->get('installed') == 1
                    && ($filter->hasStatus(ListFilterStatus::UNINSTALLED)
                        || !$filter->hasStatus(ListFilterStatus::INSTALLED))) {
                    unset($modules[$key]);

                    continue;
                }

                if ($module->database->get('installed') == 0
                    && (!$filter->hasStatus(ListFilterStatus::UNINSTALLED)
                        || $filter->hasStatus(ListFilterStatus::INSTALLED))) {
                    unset($modules[$key]);

                    continue;
                }

                if ($module->database->get('installed') == 1
                    && $module->database->get('active') == 1
                    && !$filter->hasStatus(ListFilterStatus::DISABLED)
                    && $filter->hasStatus(ListFilterStatus::ENABLED)) {
                    unset($modules[$key]);

                    continue;
                }

                if ($module->database->get('installed') == 1
                    && $module->database->get('active') == 0
                    && !$filter->hasStatus(ListFilterStatus::ENABLED)
                    && $filter->hasStatus(ListFilterStatus::DISABLED)) {
                    unset($modules[$key]);

                    continue;
                }
            }

            // Part Three : Remove addons not related to the proper source (ex Addons)
            if ($filter->origin != ListFilterOrigin::ALL) {
                if (!$module->attributes->has('origin_filter_value') &&
                    !$filter->hasOrigin(ListFilterOrigin::DISK)
                ) {
                    unset($modules[$key]);

                    continue;
                }
                if ($module->attributes->has('origin_filter_value') &&
                    !$filter->hasOrigin($module->attributes->get('origin_filter_value'))
                ) {
                    unset($modules[$key]);

                    continue;
                }
            }
        }

        return $modules;
    }

    /**
     * @return array<Module> retrieve the universe of Modules
     */
    public function getList()
    {
        return $this->getAddonsCatalogModules();
    }

    /**
     * @return array<Module> retrieve the list of native modules
     */
    public function getNativeModules()
    {
        static $nativeModules = null;

        if (null === $nativeModules) {
            $filter = new ListFilter();
            $filter->setOrigin(ListFilterOrigin::ADDONS_NATIVE);

            /** @var Module[] $nativeModules */
            $nativeModules = $this->getFilteredList($filter);

            foreach ($nativeModules as $key => $module) {
                $moduleAuthor = $module->attributes->get('author');
                if (self::NATIVE_AUTHOR !== $moduleAuthor) {
                    unset($nativeModules[$key]);
                }
            }
        }

        return $nativeModules;
    }

    /**
     * @return array<Module> retrieve the list of partners modules
     */
    public function getPartnersModules()
    {
        $filter = new ListFilter();
        $filter->setOrigin(ListFilterOrigin::ADDONS_NATIVE);

        /** @var Module[] $partnersModules */
        $partnersModules = $this->getFilteredList($filter);

        foreach ($partnersModules as $key => $module) {
            $moduleAuthor = $module->attributes->get('author');
            if (self::PARTNER_AUTHOR !== $moduleAuthor) {
                unset($partnersModules[$key]);
            }
        }

        return $partnersModules;
    }

    /**
     * @return array<Module> get the list of installed partners modules
     */
    public function getInstalledPartnersModules()
    {
        /** @var Module[] $partnersModules */
        $partnersModules = $this->getPartnersModules();

        foreach ($partnersModules as $key => $module) {
            if (1 !== $module->database->get('installed')) {
                unset($partnersModules[$key]);
            }
        }

        return $partnersModules;
    }

    /**
     * @return array<Module> get the list of not installed partners modules
     */
    public function getNotInstalledPartnersModules()
    {
        /** @var Module[] $partnersModules */
        $partnersModules = $this->getPartnersModules();

        foreach ($partnersModules as $key => $module) {
            if (0 !== $module->database->get('installed')) {
                unset($partnersModules[$key]);
            }
        }

        return $partnersModules;
    }

    private function getAddonsCatalogModules()
    {
        $modules = [];
        foreach ($this->adminModuleProvider->getCatalogModulesNames() as $name) {
            try {
                $module = $this->getModule($name);
                if ($module instanceof Module) {
                    $modules[$name] = $module;
                }
            } catch (\ParseError $e) {
                $this->logger->critical(
                    $this->translator->trans(
                        'Parse error on module %module%. %error_details%',
                        [
                            '%module%' => $name,
                            '%error_details%' => $e->getMessage(),
                        ],
                        'Admin.Modules.Notification'
                    )
                );
            } catch (Exception $e) {
                $this->logger->critical(
                    $this->translator->trans(
                        'Unexpected exception on module %module%. %error_details%',
                        [
                            '%module%' => $name,
                            '%error_details%' => $e->getMessage(),
                        ],
                        'Admin.Modules.Notification'
                    )
                );
            }
        }

        return $modules;
    }

    /**
     * Get the new module presenter class of the specified name provided.
     * It contains data from its instance, the disk, the database and from the marketplace if exists.
     *
     * @param string $name The technical name of the module
     * @param bool $skip_main_class_attributes
     *
     * @return Module
     */
    public function getModule($name, $skip_main_class_attributes = false)
    {
        if ($this->loadedModules->contains($name)) {
            return $this->loadedModules->fetch($name);
        }

        $path = $this->modulePath . $name;
        $php_file_path = $path . '/' . $name . '.php';

        /* Data which design the module class */
        $attributes = ['name' => $name];

        // Get filemtime of module main class (We do this directly with an error suppressor to go faster)
        $current_filemtime = (int) @filemtime($php_file_path);

        // We check that we have data from the marketplace
        try {
            $module_catalog_data = $this->adminModuleProvider->getCatalogModules(['name' => $name]);
            $attributes = array_merge(
                $attributes,
                (array) array_shift($module_catalog_data)
            );
        } catch (Exception $e) {
            $this->logger->alert(
                $this->translator->trans(
                    'Loading data from Addons failed. %error_details%',
                    ['%error_details%' => $e->getMessage()],
                    'Admin.Modules.Notification'
                )
            );
        }

        // Now, we check that cache is up to date
        if (isset($this->cache[$name]['disk']['filemtime']) &&
            $this->cache[$name]['disk']['filemtime'] === $current_filemtime
        ) {
            // OK, cache can be loaded and used directly
            $attributes = array_merge($attributes, $this->cache[$name]['attributes']);
            $disk = $this->cache[$name]['disk'];
        } else {
            // NOPE, we have to fulfil the cache with the module data

            $disk = [
                'filemtime' => $current_filemtime,
                'is_present' => (int) $this->moduleProvider->isOnDisk($name),
                'is_valid' => 0,
                'version' => null,
                'path' => $path,
            ];
            $main_class_attributes = [];

            if (!$skip_main_class_attributes && $this->moduleProvider->isModuleMainClassValid($name)) {
                // We load the main class of the module, and get its properties
                $tmp_module = LegacyModule::getInstanceByName($name);
                foreach (['warning', 'name', 'tab', 'displayName', 'description', 'author', 'author_address',
                    'limited_countries', 'need_instance', 'confirmUninstall', ] as $data_to_get) {
                    if (isset($tmp_module->{$data_to_get})) {
                        $main_class_attributes[$data_to_get] = $tmp_module->{$data_to_get};
                    }
                }

                $main_class_attributes['parent_class'] = get_parent_class($name);
                $main_class_attributes['is_paymentModule'] = is_subclass_of($name, 'PaymentModule');
                $main_class_attributes['is_configurable'] = (int) method_exists($tmp_module, 'getContent');

                $disk['is_valid'] = 1;
                $disk['version'] = $tmp_module->version;

                $attributes = array_merge($attributes, $main_class_attributes);
            } elseif (!$skip_main_class_attributes) {
                $main_class_attributes['warning'] = 'Invalid module class';
            } else {
                $disk['is_valid'] = 1;
            }

            $this->cache[$name]['attributes'] = $main_class_attributes;
            $this->cache[$name]['disk'] = $disk;
        }

        // Get data from database
        $database = $this->moduleProvider->findByName($name);

        $module = new Module($attributes, $disk, $database);
        $this->loadedModules->save($name, $module);

        return $module;
    }

    public function getModuleAttributes($name)
    {
        $module = $this->getModule($name);

        return $module->attributes;
    }

    /**
     * Send request to get module details on the marketplace, then merge the data received in Module instance.
     *
     * @param int $moduleId
     *
     * @return Module
     */
    public function getModuleById($moduleId)
    {
        $moduleAttributes = $this->adminModuleProvider->getModuleAttributesById($moduleId);

        $module = $this->getModule($moduleAttributes['name']);

        foreach ($moduleAttributes as $name => $value) {
            if (!$module->attributes->has($name)) {
                $module->attributes->set($name, $value);
            }
        }

        return $module;
    }

    /**
     * Function loading all installed modules on the shop. Can be used as example for ListFilter use.
     *
     * @return array
     */
    public function getInstalledModules()
    {
        $filters = new ListFilter();
        $filters->setType(ListFilterType::MODULE | ListFilterType::SERVICE)
            ->setStatus(ListFilterStatus::INSTALLED);

        return $this->getFilteredList($filters);
    }

    /**
     * Gets all installed modules as a collection.
     *
     * @return AddonsCollection
     */
    public function getInstalledModulesCollection()
    {
        $installedModules = $this->getInstalledModules();

        return AddonsCollection::createFrom($installedModules);
    }

    /**
     * Returns installed module filepaths.
     *
     * @return array
     */
    public function getInstalledModulesPaths()
    {
        $paths = [];
        $modulesFiles = Finder::create()->directories()->in(_PS_MODULES_DIR_)->depth(0);
        $installedModules = array_keys($this->getInstalledModules());

        foreach ($modulesFiles as $moduleFile) {
            if (in_array($moduleFile->getFilename(), $installedModules)) {
                $paths[] = $moduleFile->getPathname();
            }
        }

        return $paths;
    }
}
