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

namespace PrestaShop\Module\Mbo\Addons;

class ListFilter
{
    /**
     * @var int ListFilterType Specify the addon type like theme only or module only or all
     */
    public $type = ListFilterType::ALL;

    /**
     * @var int ListFilterStatus Specify if you want enabled only, disabled only or all addons
     */
    public $status = ListFilterStatus::ALL;

    /**
     * @var int ListFilterOrigin Specify if you want an addon from a specific source
     */
    public $origin = ListFilterOrigin::ALL;

    /**
     * @var array Names of all the addons to exclude from result
     */
    public $exclude = [];

    /**
     * @param int $origin
     *
     * @return self
     */
    public function addOrigin($origin)
    {
        $this->origin &= $origin;

        return $this;
    }

    /**
     * @param int $status
     *
     * @return self
     */
    public function addStatus($status)
    {
        $this->status &= $status;

        return $this;
    }

    /**
     * @param int $type
     *
     * @return self
     */
    public function addType($type)
    {
        $this->type &= $type;

        return $this;
    }

    /**
     * @param int $origin
     *
     * @return bool
     */
    public function hasOrigin($origin)
    {
        return (bool) ($this->origin & $origin);
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    public function hasStatus($status)
    {
        return (bool) ($this->status & $status);
    }

    /**
     * @param int $type
     *
     * @return bool
     */
    public function hasType($type)
    {
        return (bool) ($this->type & $type);
    }

    /**
     * @param int $origin
     *
     * @return self
     */
    public function removeOrigin($origin)
    {
        return $this->addOrigin(~$origin);
    }

    /**
     * @param int $status
     *
     * @return self
     */
    public function removeStatus($status)
    {
        return $this->addStatus(~$status);
    }

    /**
     * @param int $type
     *
     * @return self
     */
    public function removeType($type)
    {
        return $this->addType(~$type);
    }

    /**
     * @param int $origin
     *
     * @return self
     */
    public function setOrigin($origin)
    {
        $this->origin = $origin;

        return $this;
    }

    /**
     * @param int $type
     *
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param int $status
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function setExclude(array $exclude)
    {
        $this->exclude = $exclude;

        return $this;
    }
}
