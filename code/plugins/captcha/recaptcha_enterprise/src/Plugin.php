<?php
/**
 * @copyright   (C) 2023 SharkyKZ
 * @license     GPL-3.0-or-later
 */
namespace Sharky\Plugin\Captcha\RecaptchaEnterprise;

\defined('_JEXEC') || exit;

use Joomla\Application\SessionAwareWebApplicationInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Captcha\CaptchaRegistry;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\CaptchaField;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\DispatcherInterface;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * Google reCAPTCHA Enterprise plugin
 *
 * @since  1.0.0
 */
final class Plugin implements PluginInterface
{
	/**
	* Remote service invalid reason codes
	*
	* @var	string[]
	* @since  2.0.0
	*/
	private const INVALID_REASONS = [
		'INVALID_REASON_UNSPECIFIED',
		'UNKNOWN_INVALID_REASON',
		'MALFORMED',
		'EXPIRED',
		'DUPE',
		'MISSING',
		'BROWSER_ERROR',
	];

	/**
	 * Hash of the script file.
	 *
	 * @var	 string
	 * @since  1.0.0
	 */
	private const SCRIPT_HASH = '6db5f7e4';

	/**
	 * Application instance.
	 *
	 * @var	 CMSApplicationInterface
	 * @since  1.0.0
	 */
	private $app;

	/**
	 * Plugin parameters.
	 *
	 * @var	 Registry
	 * @since  1.0.0
	 */
	private $params;

	/**
	 * HTTP factory instance.
	 *
	 * @var	 HttpFactory
	 * @since  1.0.0
	 */
	private $httpFactory;

	/**
	 * Alternative Captcha instance, if set.
	 *
	 * @var	 ?Captcha
	 * @since  1.0.0
	 */
	private $captcha;

	/**
	 * Class constructor.
	 *
	 * @param   CMSApplicationInterface  $app          Application instance.
	 * @param   Registry                 $params       Plugin parameters.
	 * @param   HttpFactory              $httpFactory  HTTP factory instance.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function __construct(CMSApplicationInterface $app, Registry $params, HttpFactory $httpFactory)
	{
		$this->app = $app;
		$this->params = $params;
		$this->httpFactory = $httpFactory;
	}

	/**
	 * Unused method required to comply with broken architecture.
	 *
	 * @param   DispatcherInterface
	 *
	 * @return  $this
	 *
	 * @since   1.0.0
	 */
	public function setDispatcher(DispatcherInterface $dispatcher)
	{
		return $this;
	}

	/**
	 *  Unused method required to comply with broken architecture.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function registerListeners()
	{
	}

	/**
	 * Initialises the captcha.
	 *
	 * @param   ?string  $id  The id of the field.
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function onInit($id = null)
	{
		if (\JDEBUG) {Log::add('start', Log::INFO, 'plg_reacaptcha_enterprise_on_init'); }

		if ($this->shouldShowCaptcha())
		{
			if (\JDEBUG) { Log::add('show', Log::INFO, 'plg_reacaptcha_enterprise_on_init'); }
			return $this->getCaptcha()->initialise($id);
		}

		if (!$siteKey = $this->params->get('siteKey'))
		{
			return false;
		}

		if (!$this->app instanceof CMSWebApplicationInterface)
		{
			return false;
		}

		$document = $this->app->getDocument();

		if (!$document instanceof HtmlDocument)
		{
			return false;
		}

		$document->addScriptOptions('plg_captcha_recaptcha_enterprise.siteKey', $siteKey);
		$document->addScriptOptions('plg_captcha_recaptcha_enterprise.triggerMethod', $this->params->get('triggerMethod', 'focusin'));
		$assetManager = $document->getWebAssetManager();

		if (!$assetManager->assetExists('script', 'plg_captcha_recaptcha_enterprise.api.js'))
		{
			$languageTag = $this->app->getLanguage()->getTag();
			$assetManager->registerAsset(
				'script',
				'plg_captcha_recaptcha_enterprise.api.js',
				'https://www.google.com/recaptcha/enterprise.js?hl=' . $languageTag . '&render=' . $siteKey,
				[],
				['defer' => true, 'referrerpolicy' => 'no-referrer'],
				['core']
			);
		}

		if (!$assetManager->assetExists('script', 'plg_captcha_recaptcha_enterprise.main.js'))
		{
			$assetManager->registerAsset(
				'script',
				'plg_captcha_recaptcha_enterprise.main.js',
				'plg_captcha_recaptcha_enterprise/main.js',
				['version' => self::SCRIPT_HASH],
				['type' => 'module'],
				['plg_captcha_recaptcha_enterprise.api.js', 'core']
			);
		}

		$assetManager->useAsset('script', 'plg_captcha_recaptcha_enterprise.api.js');
		$assetManager->useAsset('script', 'plg_captcha_recaptcha_enterprise.main.js');

		return true;
	}

	/**
	 * Generates HTML field markup.
	 *
	 * @param   ?string  $name   The name of the field.
	 * @param   ?string  $id	 The id of the field.
	 * @param   ?string  $class  The class of the field.
	 *
	 * @return  string  The HTML to be embedded in the form.
	 *
	 * @since  1.0.0
	 */
	public function onDisplay($name = null, $id = null, $class = '')
	{
		if (\JDEBUG) {Log::add('start', Log::INFO, 'plg_reacaptcha_enterprise_on_display'); }

		if ($this->shouldShowCaptcha())
		{
			if (\JDEBUG) {Log::add('show', Log::INFO, 'plg_reacaptcha_enterprise_on_display'); }
			return $this->getCaptcha()->display($name, $id, $class);
		}

		$this->loadLanguage();

		if (!$this->params->get('siteKey'))
		{
			return $this->render('nokey');
		}

		$attributes = [
			'type' => 'hidden',
			'class' => 'plg-captcha-recaptcha-enterprise-hidden',
		];

		if ($name !== null && $name !== '')
		{
			$attributes['name'] = $name;
		}

		if ($id !== null && $id !== '')
		{
			$attributes['id'] = $id;
		}

		$attributes = array_map([$this, 'escape'], $attributes);

		$html = '<input ' . ArrayHelper::toString($attributes) . '>';
		$html .= '<input type="hidden" name="plg_captcha_recaptcha_enterprise_action" class="plg-captcha-recaptcha-enterprise-action">';
		$html .= $this->render('noscript');

		// Trigger token initialisation via main.js's exposed function.
		// Handles popups and dynamically loaded forms where main.js's page-load scan already ran.
		$html .= '<script>window.plgRecaptchaEnterpriseInit&&window.plgRecaptchaEnterpriseInit(document.currentScript.parentNode);</script>';

		if (\JDEBUG) {Log::add('html-rendered', Log::INFO, 'plg_reacaptcha_enterprise_on_display'); }
		
		return $html;
	}

	/**
	 * Alters form field.
	 *
	 * @param   CaptchaField       $field    Captcha field instance
	 * @param   \SimpleXMLElement  $element  XML form definition
	 *
	 * @return  void
	 *
	 * @since  1.0.0
	 */
	public function onSetupField(CaptchaField $field, \SimpleXMLElement $element)
	{
		if ($this->shouldShowCaptcha())
		{
			$this->getCaptcha()->setupField($field, $element);

			return;
		}

		$element['hiddenLabel'] = 'true';
	}

	/**
	 * Makes HTTP request to remote service to verify user's answer.
	 *
	 * @param   ?string  $code  Answer provided by user.
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 * @throws  \RuntimeException
	 */
	public function onCheckAnswer($code = null)
	{
		if (\JDEBUG) {Log::add('start', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }

		// Capture form name and email fields when available (e.g. registration, contact forms).
		$jform = $this->app instanceof CMSWebApplicationInterface
			? $this->app->getInput()->get('jform', [], 'ARRAY')
			: [];
		$formName = trim(($jform['name'] ?? '') . ' ' . ($jform['lastname'] ?? ''));
		$formEmail = $jform['email1'] ?? $jform['email'] ?? '';

		if ($this->shouldShowCaptcha())
		{
			if (\JDEBUG) {Log::add('show', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			if ($answer = $this->getCaptcha()->checkAnswer($code))
			{
				$this->setShouldShowCaptcha(false);
			}

			return $answer;
		}

		$language = $this->app->getLanguage();
		$this->loadLanguage();

		if ($code === null || $code === '')
		{
			// No answer provided, form was manipulated.
			if (\JDEBUG) {Log::add('result=fail, name=' . $formName . ', email=' . $formEmail . ', reason=Empty response - form may have been manipulated', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase('', null, null, 'fail', '', 'Empty response - form may have been manipulated', $formName, $formEmail);
			throw new \RuntimeException($language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_ERROR_EMPTY_RESPONSE'));
		}

		$apiKey = $this->params->get('apiKey');
		$projectId = $this->params->get('projectId');

		if (!$apiKey || !$projectId)
		{
			if (\JDEBUG) {Log::add('result=error, name=' . $formName . ', email=' . $formEmail . ', reason=Missing API key or project ID', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase('', null, null, 'error', '', 'Missing API key or project ID', $formName, $formEmail);
			throw new \RuntimeException($language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_NO_API_KEY'));
		}

		try
		{
			$http = $this->httpFactory->getHttp();
		}
		catch (\RuntimeException $exception)
		{


			// No HTTP transports supported.
			$this->logToDatabase('', null, null, 'error', '', 'No HTTP transports available', $formName, $formEmail);
			if (\JDEBUG) {
				Log::add('result=error, name=' . $formName . ', email=' . $formEmail . ', reason=No HTTP transports available', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); 
				throw $exception;
			}
			return !$this->params->get('strictMode');
		}

		$requestBody = json_encode([
			'event' => [
				'token' => $code,
				'siteKey' => $this->params->get('siteKey'),
				'expectedAction' => $this->app->getInput()->get('plg_captcha_recaptcha_enterprise_action', '', 'RAW'),
			],
		]);

		$expectedActionForLog = $this->app->getInput()->get('plg_captcha_recaptcha_enterprise_action', '', 'RAW');

		$url = 'https://recaptchaenterprise.googleapis.com/v1/projects/' . $projectId . '/assessments?key=' . $apiKey;

		try
		{
			$response = $http->post($url, $requestBody, ['Content-Type' => 'application/json']);
			$body = json_decode((string) $response->getBody());
		}
		catch (\RuntimeException $exception)
		{

			// Connection or transport error.
			$this->logToDatabase($expectedActionForLog, null, null, 'error', '', 'Connection error: ' . $exception->getMessage(), $formName, $formEmail);
			if (\JDEBUG) {
				Log::add('action=' . $expectedActionForLog . ', result=error, name=' . $formName . ', email=' . $formEmail . ', reason=Connection error: ' . $exception->getMessage(), Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); 
				throw $exception;
		}
			return !$this->params->get('strictMode');
		}

		// Remote service error.
		if ($body === null)
		{
			if (\JDEBUG) {Log::add('action=' . $expectedActionForLog . ', result=error, name=' . $formName . ', email=' . $formEmail . ', reason=Invalid response from reCAPTCHA service', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase($expectedActionForLog, null, null, 'error', '', 'Invalid response from reCAPTCHA service', $formName, $formEmail);

			if (\JDEBUG)
			{
				throw new \RuntimeException($language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_ERROR_INVALID_RESPONSE'));
			}

			return !$this->params->get('strictMode');
		}

		// Check if the API returned an error object.
		if (!empty($body->error))
		{
			$apiErrorMsg = $body->error->message ?? 'Unknown API error';
			if (\JDEBUG) {Log::add('action=' . $expectedActionForLog . ', result=error, name=' . $formName . ', email=' . $formEmail . ', reason=API error: ' . $apiErrorMsg, Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase($expectedActionForLog, null, null, 'error', '', 'API error: ' . $apiErrorMsg, $formName, $formEmail);

			if (\JDEBUG)
			{
				$errorMessage = $body->error->message ?? $language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_ERROR_INVALID_RESPONSE');
				throw new \RuntimeException($errorMessage);
			}

			return !$this->params->get('strictMode');
		}

		// Check token validity.
		if (!isset($body->tokenProperties->valid) || $body->tokenProperties->valid !== true)
		{
			$invalidReason = $body->tokenProperties->invalidReason ?? '';
			if (\JDEBUG) {Log::add('action=' . $expectedActionForLog . ', result=fail, name=' . $formName . ', email=' . $formEmail . ', invalidReason=' . $invalidReason . ', reason=Invalid token', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase($expectedActionForLog, null, null, 'fail', $invalidReason, 'Invalid token', $formName, $formEmail);

			if ($invalidReason !== '' && \in_array($invalidReason, self::INVALID_REASONS, true))
			{
				throw new \RuntimeException($language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_ERROR_' . $invalidReason));
			}

			return false;
		}

		// Validate expected action.
		$expectedAction = $this->app->getInput()->get('plg_captcha_recaptcha_enterprise_action', '', 'RAW');

		if (!isset($body->tokenProperties->action) || $body->tokenProperties->action !== $expectedAction)
		{
			if (\JDEBUG) {Log::add('action=' . $expectedAction . ', score=' . ($body->riskAnalysis->score ?? 'null') . ', result=fail, name=' . $formName . ', email=' . $formEmail . ', reason=Action mismatch: expected ' . $expectedAction . ', got ' . ($body->tokenProperties->action ?? 'null'), Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase($expectedAction, $body->riskAnalysis->score ?? null, null, 'fail', '', 'Action mismatch: expected ' . $expectedAction . ', got ' . ($body->tokenProperties->action ?? 'null'), $formName, $formEmail);
			throw new \RuntimeException($language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_ERROR_INVALID_ACTION'));
		}

		$score = $this->params->get('score', 0.5);

		if (!\is_float($score) || $score < 0 || $score > 1)
		{
			$score = 0.5;
		}

		if (!isset($body->riskAnalysis->score) || $body->riskAnalysis->score < $score)
		{
			if (\JDEBUG) {Log::add('action=' . $expectedAction . ', score=' . ($body->riskAnalysis->score ?? 'null') . ', threshold=' . $score . ', result=fail, name=' . $formName . ', email=' . $formEmail . ', reason=Score below threshold', Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
			$this->logToDatabase($expectedAction, $body->riskAnalysis->score ?? null, $score, 'fail', '', 'Score below threshold', $formName, $formEmail);

			if ($this->hasCaptcha())
			{
				$this->setShouldShowCaptcha(true);
			}

			throw new \RuntimeException($language->_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_ERROR_CAPTCHA_VERIFICATION'));
		}

		if (\JDEBUG) {Log::add('action=' . $expectedAction . ', score=' . $body->riskAnalysis->score . ', threshold=' . $score . ', result=pass, name=' . $formName . ', email=' . $formEmail, Log::INFO, 'plg_reacaptcha_enterprise_on_check_answer'); }
		$this->logToDatabase($expectedAction, $body->riskAnalysis->score, $score, 'pass', '', '', $formName, $formEmail);

		if ($this->hasCaptcha())
		{
			$this->setShouldShowCaptcha(false);
		}

		return true;
	}

	/**
	 * Logs a reCAPTCHA verification event to the database.
	 *
	 * @param   string       $action         The reCAPTCHA action name.
	 * @param   float|null   $score          The score returned by reCAPTCHA.
	 * @param   float|null   $threshold      The configured score threshold.
	 * @param   string       $result         Result: 'pass', 'fail', or 'error'.
	 * @param   string       $invalidReason  The invalid reason code from reCAPTCHA.
	 * @param   string       $errorMessage   Additional error/context message.
	 * @param   string       $formName       Name submitted on the form (if available).
	 * @param   string       $formEmail      Email submitted on the form (if available).
	 *
	 * @return  void
	 *
	 * @since   2.1.0
	 */
	private function logToDatabase(
		string $action,
		?float $score,
		?float $threshold,
		string $result,
		string $invalidReason = '',
		string $errorMessage = '',
		string $formName = '',
		string $formEmail = ''
	): void {
		if (!$this->params->get('enableLogging'))
		{
			return;
		}

		try
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);

			$logEntry = (object) [
				'log_date'       => Factory::getDate()->toSql(),
				'ip_address'     => $this->app instanceof CMSWebApplicationInterface
					? $this->app->getInput()->server->getString('REMOTE_ADDR', '')
					: '',
				'action'         => $action,
				'score'          => $score,
				'threshold'      => $threshold,
				'result'         => $result,
				'invalid_reason' => $invalidReason,
				'error_message'  => $errorMessage,
				'page_url'       => $this->app instanceof CMSWebApplicationInterface
					? substr($this->app->getInput()->server->getString('REQUEST_URI', ''), 0, 2048)
					: '',
				'user_id'        => method_exists($this->app, 'getIdentity')
					? (int) ($this->app->getIdentity()?->id ?? 0)
					: 0,
				'form_name'      => substr($formName, 0, 400),
				'form_email'     => substr($formEmail, 0, 320),
			];

			$db->insertObject('#__recaptcha_enterprise_log', $logEntry);

			// Probabilistic pruning (~5% of requests) to avoid overhead on every call.
			if (random_int(1, 20) === 1)
			{
				$this->pruneLog($db);
			}
		}
		catch (\Throwable $e)
		{
			// Logging should never break the captcha flow.
			Log::add('reCAPTCHA logging failed: ' . $e->getMessage(), Log::WARNING, 'plg_captcha_recaptcha_enterprise');
		}
	}

	/**
	 * Prunes the log table to stay within configured retention limits.
	 *
	 * Deletes rows older than the configured number of days, then trims
	 * excess rows beyond the configured maximum count (oldest first).
	 *
	 * @param   DatabaseInterface  $db  Database driver instance.
	 *
	 * @return  void
	 *
	 * @since   2.1.0
	 */
	private function pruneLog(DatabaseInterface $db): void
	{
		$maxDays = (int) $this->params->get('logRetentionDays', 45);
		$maxRows = (int) $this->params->get('logMaxRows', 1000);

		// Delete entries older than retention period.
		if ($maxDays > 0)
		{
			$cutoff = Factory::getDate('now - ' . $maxDays . ' days')->toSql();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__recaptcha_enterprise_log'))
				->where($db->quoteName('log_date') . ' < ' . $db->quote($cutoff));
			$db->setQuery($query)->execute();
		}

		// Trim to max row count (keep newest).
		if ($maxRows > 0)
		{
			$countQuery = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__recaptcha_enterprise_log'));
			$total = (int) $db->setQuery($countQuery)->loadResult();

			if ($total > $maxRows)
			{
				// Find the ID threshold: keep only the newest $maxRows rows.
				$idQuery = $db->getQuery(true)
					->select($db->quoteName('id'))
					->from($db->quoteName('#__recaptcha_enterprise_log'))
					->order($db->quoteName('id') . ' DESC');
				$minKeepId = (int) $db->setQuery($idQuery, $maxRows - 1, 1)->loadResult();

				if ($minKeepId > 0)
				{
					$deleteQuery = $db->getQuery(true)
						->delete($db->quoteName('#__recaptcha_enterprise_log'))
						->where($db->quoteName('id') . ' < ' . (int) $minKeepId);
					$db->setQuery($deleteQuery)->execute();
				}
			}
		}
	}

	private function escape(?string $string): string
	{
		return $string ? htmlspecialchars($string, \ENT_QUOTES|\ENT_SUBSTITUTE|\ENT_HTML5, 'UTF-8') : (string) $string;
	}

	private function render(string $layout): string
	{
		$html = '';
		$file = PluginHelper::getLayoutPath('captcha', 'recaptcha_enterprise', $layout);

		if (!is_file($file))
		{
			return '';
		}

		$data = [
			'language' => $this->app->getLanguage(),
		];

		ob_start();

		(static function ()
		{
			extract(func_get_arg(1));

			include func_get_arg(0);
		})($file, $data);

		$html .= ob_get_clean();

		return $html;
	}

	private function loadLanguage(): void
	{
		$this->app->getLanguage()->load('plg_captcha_recaptcha_enterprise', \JPATH_ADMINISTRATOR);
	}

	private function setShouldShowCaptcha(bool $value): void
	{
		if (!$this->app instanceof SessionAwareWebApplicationInterface)
		{
			return;
		}

		if ($value)
		{
			$this->app->getSession()->set('plg_captcha_recaptcha_enterprise.showCaptcha', true);

			return;
		}

		$this->app->getSession()->remove('plg_captcha_recaptcha_enterprise.showCaptcha');
	}

	private function shouldShowCaptcha(): bool
	{
		if (!$this->hasCaptcha())
		{
			return false;
		}

		if (!$this->app instanceof SessionAwareWebApplicationInterface)
		{
			return false;
		}

		return $this->app->getSession()->has('plg_captcha_recaptcha_enterprise.showCaptcha');
	}

	private function hasCaptcha(): bool
	{
		if (!$captcha = $this->params->get('captcha'))
		{
			return false;
		}

		if ($captcha === 'recaptcha_enterprise')
		{
			return false;
		}

		if (version_compare(\JVERSION, '5.0', '>='))
		{
			$container = Factory::getContainer();

			if ($container->has(CaptchaRegistry::class) && $container->get(CaptchaRegistry::class)->has($captcha))
			{
				return true;
			}
		}

		return PluginHelper::isEnabled('captcha', $captcha);
	}

	private function getCaptcha(): Captcha
	{
		if ($this->captcha === null)
		{
			$this->captcha = Captcha::getInstance($this->params->get('captcha'));
		}

		return $this->captcha;
	}
}
