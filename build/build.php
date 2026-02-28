#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Sharky\Joomla\PluginBuildScript\Script;

(
	new class(
		str_replace('\\', '/', dirname(__DIR__)),
		str_replace('\\', '/', __DIR__),
		'recaptcha_enterprise',
		'captcha',
		'joomla-recaptcha-enterprise-plugin',
		'FromOrbonia',
		'Captcha - reCAPTCHA Enterprise',
		'Google reCAPTCHA Enterprise plugin for Joomla!.',
		'(6\.|5\.|4\.)',
		'7.2.5',
		$argv[1] ?? null,
	) extends Script {
		public function build(): void
		{
			$plugin = $this->pluginDirectory . '/src/Plugin.php';
			$script = $this->mediaDirectory . '/js/main.js';

			$hash = substr(hash_file('sha1', $script, false), 0, 8);
			$code = file_get_contents($plugin);

			$pattern = '/(private\s+const\s+SCRIPT_HASH\s+=\s+\')(.*)(\';)/';
			$code = preg_replace($pattern, '${1}' . $hash . '$3', $code);

			file_put_contents($plugin, $code);

			parent::build();

			// The upstream library hardcodes "master" in changelogurl.
			// Fix it to use "main" which is the actual default branch.
			$updatesFile = $this->rootPath . '/updates/updates.xml';

			if (is_file($updatesFile))
			{
				$xml = file_get_contents($updatesFile);
				$xml = str_replace('/' . $this->repositoryName . '/master/', '/' . $this->repositoryName . '/main/', $xml);
				file_put_contents($updatesFile, $xml);
			}
		}
	}
)->build();
