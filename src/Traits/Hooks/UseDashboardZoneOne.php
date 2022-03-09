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

namespace PrestaShop\Module\Mbo\Traits\Hooks;

use Db;
use Exception;

trait UseDashboardZoneOne
{
    /**
     * Display "Advices and updates" block on the left column of the dashboard
     *
     * @param array $params
     *
     * @return false|string
     */
    public function hookDashboardZoneOne(array $params)
    {
        if (!$this->get('mbo.addons.data_provider')->isUserAuthenticated()) {
            $connectButton = $this->get('mbo.addons.toolbar')->getAddonsConnectButton();
            $this->smarty->assign(
                [
                    'connect_button' => $connectButton,
                ]
            );

            return $this->display($this->name, 'dashboard-zone-one-not-connected.tpl');
        } else {
            $weekAdvice = $this->get('mbo.addons.week_advice_provider')->getByIsoCode(
                $this->context->language->iso_code
            );

            // assign var to smarty
            $this->smarty->assign([
                'img_path' => $this->imgPath,
                'advice' => $weekAdvice,
                'practical_links' => $this->get('mbo.addons.static_link_provider')->getDashboardPracticalLinks(),
            ]);

            return $this->display($this->name, 'dashboard-zone-one-connected.tpl');
        }
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function bootUseDashboardZoneOne(): void
    {
        if (method_exists($this, 'addAdminControllerMedia')) {
            $this->addAdminControllerMedia('loadMediaDashboardZoneOne');
        }
    }

    /**
     * Add JS and CSS file
     *
     * @see \PrestaShop\Module\Mbo\Traits\Hooks\UseAdminControllerSetMedia
     *
     * @return void
     */
    protected function loadMediaDashboardZoneOne(): void
    {
        $this->context->controller->addJs($this->getPathUri() . 'views/js/addons-connector.js?v=' . $this->version);
    }

    public function useDashboardZoneOneExtraOperations()
    {
        //Update module position in Dashboard
        $query = 'SELECT id_hook FROM ' . _DB_PREFIX_ . "hook WHERE name = 'dashboardZoneOne'";

        /** @var array $result */
        $result = Db::getInstance()->ExecuteS($query);
        $id_hook = $result['0']['id_hook'];

        $this->updatePosition((int) $id_hook, false);
    }
}
