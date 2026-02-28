<?php
/**
 * @copyright   (C) 2023 SharkyKZ
 * @license     GPL-3.0-or-later
 */

defined('_JEXEC') || exit;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;

/**
 * Plugin installer script.
 */
final class PlgCaptchaRecaptcha_V3InstallerScript
{
	/**
	 * Minimum supported Joomla! version.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $joomlaMinimum = '4.0';

	/**
	 * Next unsupported Joomla! version.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $joomlaUnsupported = '7.0';

	/**
	 * Minimum supported PHP version.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $phpMinimum = '7.2.5';

	/**
	 * Next unsupported PHP version.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $phpUnsupported = '9.0';

	/**
	 * Function called before extension installation/update/removal procedure commences.
	 *
	 * @param   string                                 $type    The type of change (install, update, discover_install or uninstall).
	 * @param   Joomla\CMS\Installer\InstallerAdapter  $parent  The class calling this method.
	 *
	 * @return  bool  Returns true if installation can proceed.
	 *
	 * @since   1.0.0
	 */
	public function preflight($type, $parent)
	{
		if ($type === 'uninstall')
		{
			return true;
		}

		if (version_compare(JVERSION, $this->joomlaMinimum, '<'))
		{
			return false;
		}

		if (version_compare(JVERSION, $this->joomlaUnsupported, '>=') && !(new Version)->isInDevelopmentState())
		{
			return false;
		}

		if (version_compare(PHP_VERSION, $this->phpMinimum, '<'))
		{
			Log::add(Text::sprintf('PLG_CAPTCHA_RECAPTCHA_V3_INSTALL_PHP_MINIMUM', $this->phpMinimum), Log::WARNING, 'jerror');

			return false;
		}

		if (version_compare(PHP_VERSION, $this->phpUnsupported, '>='))
		{
			Log::add(Text::sprintf('PLG_CAPTCHA_RECAPTCHA_V3_INSTALL_PHP_UNSUPPORTED', $this->phpUnsupported), Log::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Function called after extension installation/update procedure.
	 *
	 * @param   string                                 $type    The type of change (install, update, discover_install or uninstall).
	 * @param   Joomla\CMS\Installer\InstallerAdapter  $parent  The class calling this method.
	 *
	 * @return  bool
	 *
	 * @since   2.1.0
	 */
	public function postflight($type, $parent)
	{
		if ($type === 'uninstall')
		{
			return true;
		}

		// Ensure the log table exists on updates (fresh installs are handled by the <install> SQL).
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = "CREATE TABLE IF NOT EXISTS `#__recaptcha_v3_log` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`log_date` datetime NOT NULL,
			`ip_address` varchar(45) NOT NULL DEFAULT '',
			`action` varchar(255) NOT NULL DEFAULT '',
			`score` decimal(3,2) DEFAULT NULL,
			`threshold` decimal(3,2) DEFAULT NULL,
			`result` varchar(20) NOT NULL DEFAULT '',
			`invalid_reason` varchar(50) NOT NULL DEFAULT '',
			`error_message` text,
			`page_url` varchar(2048) NOT NULL DEFAULT '',
			`user_id` int unsigned NOT NULL DEFAULT 0,
			`form_name` varchar(400) NOT NULL DEFAULT '',
			`form_email` varchar(320) NOT NULL DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `idx_log_date` (`log_date`),
			KEY `idx_result` (`result`),
			KEY `idx_ip_address` (`ip_address`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add('Failed to create recaptcha_v3_log table: ' . $e->getMessage(), Log::ERROR, 'jerror');

			return false;
		}

		// Add form_name and form_email columns if upgrading from an older version.
		try
		{
			$columns = $db->getTableColumns('#__recaptcha_v3_log');

			if (!isset($columns['form_name']))
			{
				$db->setQuery("ALTER TABLE `#__recaptcha_v3_log` ADD COLUMN `form_name` varchar(400) NOT NULL DEFAULT '' AFTER `user_id`")->execute();
			}

			if (!isset($columns['form_email']))
			{
				$db->setQuery("ALTER TABLE `#__recaptcha_v3_log` ADD COLUMN `form_email` varchar(320) NOT NULL DEFAULT '' AFTER `form_name`")->execute();
			}
		}
		catch (\RuntimeException $e)
		{
			Log::add('Failed to add form_name/form_email columns: ' . $e->getMessage(), Log::WARNING, 'jerror');
		}

		return true;
	}
}
