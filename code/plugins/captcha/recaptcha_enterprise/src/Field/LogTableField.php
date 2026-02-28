<?php
/**
 * @copyright   (C) 2023 SharkyKZ
 * @license     GPL-3.0-or-later
 */
namespace Sharky\Plugin\Captcha\RecaptchaEnterprise\Field;

\defined('_JEXEC') || exit;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * Renders the reCAPTCHA Enterprise log table inside the plugin configuration.
 *
 * @since  2.1.0
 */
final class LogTableField extends FormField
{
	/**
	 * @var  string
	 */
	protected $type = 'LogTable';

	/**
	 * Rows per page.
	 *
	 * @var  int
	 */
	private const PER_PAGE = 20;

	/**
	 * Columns displayed in the table.
	 *
	 * @var  string[]
	 */
	private const COLUMNS = [
		'id',
		'log_date',
		'ip_address',
		'action',
		'score',
		'threshold',
		'result',
		'invalid_reason',
		'error_message',
		'page_url',
		'user_id',
		'form_name',
		'form_email',
	];

	/**
	 * @return  string
	 */
	protected function getInput()
	{
		return '';
	}

	/**
	 * @return  string
	 */
	protected function getLabel()
	{
		return '';
	}

	/**
	 * Override to render the full log viewer widget.
	 *
	 * @param   array  $options  Layout options (unused).
	 *
	 * @return  string
	 */
	public function renderField($options = [])
	{
		$app = Factory::getApplication();
		$db  = Factory::getContainer()->get(DatabaseInterface::class);

		// Handle clear action.
		if ($app->getInput()->get('recaptcha_log_action') === 'clear'
			&& $app->getSession()->checkToken('get'))
		{
			$db->truncateTable('#__recaptcha_enterprise_log');
			$app->enqueueMessage(Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_CLEARED'));
		}

		// Filters from query string.
		$filterResult = $app->getInput()->getString('recaptcha_log_filter_result', '');
		$filterIp     = $app->getInput()->getString('recaptcha_log_filter_ip', '');
		$filterAction = $app->getInput()->getString('recaptcha_log_filter_action', '');
		$page         = max(1, $app->getInput()->getInt('recaptcha_log_page', 1));
		$offset       = ($page - 1) * self::PER_PAGE;

		// Count query.
		$countQuery = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__recaptcha_enterprise_log'));

		$this->applyFilters($countQuery, $filterResult, $filterIp, $filterAction, $db);

		$total = (int) $db->setQuery($countQuery)->loadResult();
		$totalPages = max(1, (int) ceil($total / self::PER_PAGE));

		if ($page > $totalPages)
		{
			$page   = $totalPages;
			$offset = ($page - 1) * self::PER_PAGE;
		}

		// Data query.
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__recaptcha_enterprise_log'))
			->order($db->quoteName('id') . ' DESC');

		$this->applyFilters($query, $filterResult, $filterIp, $filterAction, $db);

		$rows = $db->setQuery($query, $offset, self::PER_PAGE)->loadObjectList();

		// Build base URL, preserving existing query params.
		$baseUri = clone Uri::getInstance();
		$baseUri->delVar('recaptcha_log_page');

		// Build HTML.
		$html = '<div class="recaptcha-log-viewer">';
		$html .= $this->renderFilters($filterResult, $filterIp, $filterAction, $baseUri);
		$html .= $this->renderSummary($total, $page, $totalPages);
		$html .= $this->renderTable($rows);
		$html .= $this->renderPagination($page, $totalPages, $baseUri);
		$html .= $this->renderClearButton($baseUri);
		$html .= '</div>';

		return $html;
	}

	/**
	 * Apply active filters to a query.
	 */
	private function applyFilters($query, string $filterResult, string $filterIp, string $filterAction, DatabaseInterface $db): void
	{
		if ($filterResult !== '')
		{
			$query->where($db->quoteName('result') . ' = ' . $db->quote($filterResult));
		}

		if ($filterIp !== '')
		{
			$query->where($db->quoteName('ip_address') . ' = ' . $db->quote($filterIp));
		}

		if ($filterAction !== '')
		{
			$query->where($db->quoteName('action') . ' LIKE ' . $db->quote('%' . $db->escape($filterAction, true) . '%'));
		}
	}

	/**
	 * Render the filter form.
	 */
	private function renderFilters(string $filterResult, string $filterIp, string $filterAction, Uri $baseUri): string
	{
		$lang = Factory::getApplication()->getLanguage();
		$lang->load('plg_captcha_recaptcha_enterprise', JPATH_ADMINISTRATOR);

		$html  = '<div class="recaptcha-log-filters mb-3 p-3 bg-light border rounded">';
		$html .= '<form method="get" class="row g-2 align-items-end">';

		// Preserve all existing query params as hidden fields.
		$uri = Uri::getInstance();

		foreach ($uri->getQuery(true) as $key => $value)
		{
			if (strpos($key, 'recaptcha_log_') === 0)
			{
				continue;
			}

			$html .= '<input type="hidden" name="' . $this->escape($key) . '" value="' . $this->escape($value) . '">';
		}

		// Result filter.
		$html .= '<div class="col-auto">';
		$html .= '<label class="form-label fw-bold">' . Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_RESULT') . '</label>';
		$html .= '<select name="recaptcha_log_filter_result" class="form-select form-select-sm">';
		$html .= '<option value="">' . Text::_('JALL') . '</option>';

		foreach (['pass', 'fail', 'error'] as $opt)
		{
			$sel = ($filterResult === $opt) ? ' selected' : '';
			$html .= '<option value="' . $opt . '"' . $sel . '>' . ucfirst($opt) . '</option>';
		}

		$html .= '</select></div>';

		// IP filter.
		$html .= '<div class="col-auto">';
		$html .= '<label class="form-label fw-bold">' . Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_IP_ADDRESS') . '</label>';
		$html .= '<input type="text" name="recaptcha_log_filter_ip" value="' . $this->escape($filterIp) . '" class="form-control form-control-sm" placeholder="e.g. 192.168.1.1">';
		$html .= '</div>';

		// Action filter.
		$html .= '<div class="col-auto">';
		$html .= '<label class="form-label fw-bold">' . Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_ACTION') . '</label>';
		$html .= '<input type="text" name="recaptcha_log_filter_action" value="' . $this->escape($filterAction) . '" class="form-control form-control-sm" placeholder="e.g. login">';
		$html .= '</div>';

		// Buttons.
		$html .= '<div class="col-auto">';
		$html .= '<button type="submit" class="btn btn-sm btn-primary">' . Text::_('JSEARCH_FILTER_SUBMIT') . '</button>';

		$clearUri = clone $baseUri;
		$clearUri->delVar('recaptcha_log_filter_result');
		$clearUri->delVar('recaptcha_log_filter_ip');
		$clearUri->delVar('recaptcha_log_filter_action');

		$html .= ' <a href="' . $clearUri->toString() . '" class="btn btn-sm btn-secondary">' . Text::_('JSEARCH_FILTER_CLEAR') . '</a>';
		$html .= '</div>';

		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the summary line.
	 */
	private function renderSummary(int $total, int $page, int $totalPages): string
	{
		return '<div class="mb-2"><small class="text-muted">'
			. Text::sprintf('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_SUMMARY', $total, $page, $totalPages)
			. '</small></div>';
	}

	/**
	 * Render the data table.
	 */
	private function renderTable(array $rows): string
	{
		$lang = Factory::getApplication()->getLanguage();
		$lang->load('plg_captcha_recaptcha_enterprise', JPATH_ADMINISTRATOR);

		$html = '<div class="table-responsive"><table class="table table-striped table-sm recaptcha-log-table">';
		$html .= '<thead><tr>';

		foreach (self::COLUMNS as $col)
		{
			$langKey = 'PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_' . strtoupper($col);
			$html .= '<th>' . Text::_($langKey) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		if (empty($rows))
		{
			$html .= '<tr><td colspan="' . \count(self::COLUMNS) . '" class="text-center text-muted">'
				. Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_NO_RECORDS')
				. '</td></tr>';
		}
		else
		{
			foreach ($rows as $row)
			{
				$html .= '<tr>';

				foreach (self::COLUMNS as $col)
				{
					$value = $row->{$col} ?? '';
					$html .= '<td>' . $this->formatCell($col, $value) . '</td>';
				}

				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table></div>';

		return $html;
	}

	/**
	 * Format a cell value for display.
	 */
	private function formatCell(string $column, $value): string
	{
		if ($value === '' || $value === null)
		{
			return '<span class="text-muted">-</span>';
		}

		switch ($column)
		{
			case 'result':
				$badgeClass = match ($value) {
					'pass'  => 'bg-success',
					'fail'  => 'bg-danger',
					'error' => 'bg-warning text-dark',
					default => 'bg-secondary',
				};

				return '<span class="badge ' . $badgeClass . '">' . $this->escape(ucfirst($value)) . '</span>';

			case 'score':
			case 'threshold':
				return $this->escape(number_format((float) $value, 2));

			case 'page_url':
				$short = strlen($value) > 80 ? substr($value, 0, 80) . '&hellip;' : $this->escape($value);

				return '<span title="' . $this->escape($value) . '">' . $short . '</span>';

			case 'error_message':
				$short = strlen($value) > 60 ? substr($value, 0, 60) . '&hellip;' : $this->escape($value);

				return '<span title="' . $this->escape($value) . '">' . $short . '</span>';

			default:
				return $this->escape((string) $value);
		}
	}

	/**
	 * Render pagination controls.
	 */
	private function renderPagination(int $currentPage, int $totalPages, Uri $baseUri): string
	{
		if ($totalPages <= 1)
		{
			return '';
		}

		$html = '<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">';

		// Previous button.
		if ($currentPage > 1)
		{
			$uri = clone $baseUri;
			$uri->setVar('recaptcha_log_page', $currentPage - 1);
			$html .= '<li class="page-item"><a class="page-link" href="' . $uri->toString() . '">&laquo;</a></li>';
		}
		else
		{
			$html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
		}

		// Page numbers (show max 7 pages around current).
		$start = max(1, $currentPage - 3);
		$end   = min($totalPages, $currentPage + 3);

		if ($start > 1)
		{
			$uri = clone $baseUri;
			$uri->setVar('recaptcha_log_page', 1);
			$html .= '<li class="page-item"><a class="page-link" href="' . $uri->toString() . '">1</a></li>';

			if ($start > 2)
			{
				$html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
			}
		}

		for ($i = $start; $i <= $end; $i++)
		{
			$uri = clone $baseUri;
			$uri->setVar('recaptcha_log_page', $i);
			$active = ($i === $currentPage) ? ' active' : '';
			$html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $uri->toString() . '">' . $i . '</a></li>';
		}

		if ($end < $totalPages)
		{
			if ($end < $totalPages - 1)
			{
				$html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
			}

			$uri = clone $baseUri;
			$uri->setVar('recaptcha_log_page', $totalPages);
			$html .= '<li class="page-item"><a class="page-link" href="' . $uri->toString() . '">' . $totalPages . '</a></li>';
		}

		// Next button.
		if ($currentPage < $totalPages)
		{
			$uri = clone $baseUri;
			$uri->setVar('recaptcha_log_page', $currentPage + 1);
			$html .= '<li class="page-item"><a class="page-link" href="' . $uri->toString() . '">&raquo;</a></li>';
		}
		else
		{
			$html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
		}

		$html .= '</ul></nav>';

		return $html;
	}

	/**
	 * Render the "Clear Log" button.
	 */
	private function renderClearButton(Uri $baseUri): string
	{
		$clearUri = clone $baseUri;
		$clearUri->setVar('recaptcha_log_action', 'clear');
		$clearUri->setVar(Factory::getApplication()->getSession()->getFormToken(), '1');

		return '<div class="mt-3 text-end">'
			. '<a href="' . $clearUri->toString() . '" class="btn btn-danger btn-sm" onclick="return confirm(\'' . Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_CLEAR_CONFIRM', true) . '\')">'
			. '<span class="icon-trash" aria-hidden="true"></span> '
			. Text::_('PLG_CAPTCHA_RECAPTCHA_ENTERPRISE_LOG_CLEAR')
			. '</a></div>';
	}

	/**
	 * Escape a string for safe HTML output.
	 */
	private function escape(?string $string): string
	{
		return htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
