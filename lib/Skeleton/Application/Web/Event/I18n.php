<?php

declare(strict_types=1);

/**
 * I18n Context
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Application\Web\Event;

class I18n extends \Skeleton\Core\Application\Event {
	/**
	 * Get the translator extractor for this app
	 *
	 * @access public
	 * @return \Skeleton\I18n\Translator\Extractor $extractor
	 */
	public function get_translator_extractor(): \Skeleton\I18n\Translator\Extractor {
		// Twig templates are the default for a skeleton app
		if (!file_exists($this->application->template_path)) {
			throw new \Exception('Cannot get translator_extractor, template path does not exist');
		}

		$translator_extractor_twig = new \Skeleton\I18n\Translator\Extractor\Twig();
		$translator_extractor_twig->set_template_path($this->application->template_path);
		return $translator_extractor_twig;
	}

	/**
	 * Get the translator storage for this app
	 *
	 * @access public
	 * @return \Skeleton\I18n\Translator\Storage $storage
	 */
	public function get_translator_storage(): \Skeleton\I18n\Translator\Storage {
		$default_configuration = \Skeleton\I18n\Translator\Storage\Po::get_default_configuration();

		if (!isset($default_configuration['storage_path'])) {
			throw new \Exception('No po storage path defined, cannot setup translation');
		}

		return new \Skeleton\I18n\Translator\Storage\Po();
	}

	/**
	 * Get the translator service for this app
	 *
	 * @access public
	 * @return ?\Skeleton\I18n\Translator\Service $service
	 */
	public function get_translator_service(): ?\Skeleton\I18n\Translator\Service {
		// By default we do not use a translator service
		return null;
	}

	/**
	 * Get translator
	 *
	 * @access public
	 * @return \Skeleton\I18n\Translator $translator
	 */
	public function get_translator(): ?\Skeleton\I18n\Translator {
		try {
			$translator_storage = $this->application->call_event('i18n', 'get_translator_storage');
			$translator_extractor = $this->application->call_event('i18n', 'get_translator_extractor');
		} catch (\Exception $e) {
			return null;
		}

		try {
			$translator_service = $this->application->call_event('i18n', 'get_translator_service');
		} catch (\Exception $e) {
			$translator_service = null;
		}

		$translator = new \Skeleton\I18n\Translator($this->application->name);
		$translator->set_translator_storage($translator_storage);
		$translator->set_translator_extractor($translator_extractor);
		if (isset($translator_service) === true) {
			$translator->set_translator_service($translator_service);
		}
		return $translator;
	}

	/**
	 * Detect the language
	 *
	 * @access public
	 * @return \Skeleton\I18n\LanguageInterface $language
	 */
	public function detect_language(): \Skeleton\I18n\LanguageInterface {
		$language_interface = \Skeleton\I18n\Config::$language_interface;

		// Check for requested language in $_GET
		if (isset($_GET['language'])) {
			try {
				return $language_interface::get_by_name_short($_GET['language']);
			} catch (\Exception $e) {
			}
		}

		// Check if a language is stored in session
		if (isset($_SESSION['language'])) {
			return $_SESSION['language'];
		}

		// Negotiate a language based on HTTP_ACEPT_LANGUAGE
		try {
			$languages = $language_interface::get_all();
			$all_languages = [];
			$available_languages = [];

			foreach ($languages as $language) {
				$available_languages[] = $language->name_short;
				$all_languages[$language->name_short] = $language;
			}

			$matching_language = \Skeleton\I18n\Util::get_best_matching_language(
				$_SERVER['HTTP_ACCEPT_LANGUAGE'], $available_languages
			);

			if ($matching_language === false) {
				throw new \Exception('No matching language found');
			}

			$language = $all_languages[$matching_language];
		} catch (\Exception $e) {
			$language = $language_interface::get_by_name_short($this->application->config->default_language);
		}

		return $language;
	}
}
