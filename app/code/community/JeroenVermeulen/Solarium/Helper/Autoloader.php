<?php
/**
 * JeroenVermeulen_Solarium
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this Module to
 * newer versions in the future.
 *
 * @category    JeroenVermeulen
 * @package     JeroenVermeulen_Solarium
 * @copyright   Copyright (c) 2014 Jeroen Vermeulen (http://www.jeroenvermeulen.eu)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class JeroenVermeulen_Solarium_Helper_Autoloader
 */
class JeroenVermeulen_Solarium_Helper_Autoloader extends Mage_Core_Helper_Abstract
{

    /**
     * Prepends our autoloader, so we can load the extra library classes when they are needed.
     */
    public
    function register()
    {
        $registryKey = 'JeroenVermeulen_Solarium_Autoloader_registered';
        if (!Mage::registry( $registryKey )) {
            Mage::register( $registryKey, true );
            spl_autoload_register( array( $this, 'load' ), true, true );
        }
    }

    /**
     * This function can autoload classes starting with:
     * - Solarium
     * - Symfony\Component\EventDispatcher
     *
     * @param string $class
     */
    public static
    function load(
        $class
    ) {
        if (preg_match( '#^(Solarium|Symfony\\\\Component\\\\EventDispatcher)\b#', $class )) {
            $phpFile = Mage::getBaseDir( 'lib' ) . DIRECTORY_SEPARATOR . str_replace(
                    '\\',
                    DIRECTORY_SEPARATOR,
                    $class
                ) . '.php';
            /** @noinspection PhpIncludeInspection */
            require_once( $phpFile );
        }
    }

}