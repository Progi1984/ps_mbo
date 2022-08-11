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
declare(strict_types=1);

namespace PrestaShop\Module\Mbo\Module\Workflow;

use Exception;
use PrestaShop\Module\Mbo\Module\Module;
use PrestaShop\Module\Mbo\Module\Repository;
use PrestaShop\Module\Mbo\Module\TransitionModule;
use PrestaShop\PrestaShop\Core\Module\ModuleManager;

class TransitionsManager
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var Repository
     */
    private $moduleRepository;

    public function __construct(ModuleManager $moduleManager, Repository $moduleRepository)
    {
        $this->moduleManager = $moduleManager;
        $this->moduleRepository = $moduleRepository;
    }

    public function uninstalledToEnabledAndMobileEnabled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->install($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileEnabledToEnabledAndMobileDisabled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->enable($transitionModule, $context) && $this->disableOnMobile($transitionModule);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileEnabledToDisabledAndMobileEnabled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->disable($transitionModule, $context) && $this->enableOnMobile($transitionModule);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileEnabledToReset(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->reset($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileEnabledToUpgraded(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->upgrade($transitionModule, $context);
    }

    public function enabledAndMobileEnabledToConfigured(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileEnabledToUninstalled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->uninstall($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileDisabledToInstalled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->install($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileDisabledToEnabledAndMobileEnabled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->enable($transitionModule, $context) && $this->enableOnMobile($transitionModule);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileDisabledToReset(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->reset($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileDisabledToUpgraded(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->upgrade($transitionModule, $context);
    }

    public function enabledAndMobileDisabledToConfigured(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function enabledAndMobileDisabledToUninstalled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->uninstall($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function disabledAndMobileEnabledToInstalled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->install($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function disabledAndMobileEnabledToEnabledAndMobileEnabled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->enable($transitionModule, $context) && $this->enableOnMobile($transitionModule);
    }

    /**
     * @throws Exception
     */
    public function disabledAndMobileEnabledToReset(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->reset($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    public function disabledAndMobileEnabledToUpgraded(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->upgrade($transitionModule, $context);
    }

    public function disabledAndMobileEnabledToConfigured(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function disabledAndMobileEnabledToUninstalled(TransitionModule $transitionModule, string $marking, array $context): bool
    {
        return $this->uninstall($transitionModule, $context);
    }

    /**
     * @throws Exception
     */
    private function enable(TransitionModule $transitionModule, ?array $context = []): bool
    {
        if ($transitionModule->isActive()) {
            return true;
        }

        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->enable($moduleName)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onEnable();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function disable(TransitionModule $transitionModule, ?array $context = []): bool
    {
        if (!$transitionModule->isActive()) {
            return true;
        }

        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->disable($moduleName)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onDisable();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function enableOnMobile(TransitionModule $transitionModule, ?array $context = []): bool
    {
        if ($transitionModule->isActiveOnMobile()) {
            return true;
        }

        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->enableMobile($moduleName)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onMobileEnable();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function disableOnMobile(TransitionModule $transitionModule, ?array $context = []): bool
    {
        if (!$transitionModule->isActiveOnMobile()) {
            return true;
        }

        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->disableMobile($moduleName)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onMobileDisable();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function reset(TransitionModule $transitionModule, ?array $context = []): bool
    {
        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->reset($moduleName)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onReset();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function upgrade(TransitionModule $transitionModule, ?array $context = []): bool
    {
        $source = null;
        if (isset($context['source'])) {
            $source = (string) $context['source'];
        }

        // We are calling install on purpose. If install is called for an already installed module, it'll perform an upgrade
        // Plus the "install" method allows us to provide an external source
        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->install($moduleName, $source)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onUpgrade($transitionModule->getVersion());
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function uninstall(TransitionModule $transitionModule, ?array $context = []): bool
    {
        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->uninstall($moduleName)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onUninstall();
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private function install(TransitionModule $transitionModule, ?array $context = []): bool
    {
        $source = null;
        if (isset($context['source'])) {
            $source = (string) $context['source'];
        }

        $moduleName = $transitionModule->getName();
        if ($this->moduleManager->install($moduleName, $source)) {
            $module = $this->getModuleInstance($moduleName);
            if (null === $module) {
                return false;
            }

            return $module->onInstall();
        }

        return false;
    }

    private function getModuleInstance(string $moduleName)
    {
        return $this->moduleRepository->getModule($moduleName);
    }
}
