<?php

declare(strict_types=1);

/**
 * Singleton, you can get an instance with Web_Template::Get()
 *
 * Embeds the Template object
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Application\Web;

class Template {
	/**
	 * Unique
	 */
	private int $unique = 0;

	/**
	 * Template
	 *
	 * @access private
	 */
	private ?\Skeleton\Template\Template $template = null;

	/**
	 * Template
	 *
	 * @access private
	 */
	private static ?self $web_template = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->template = new \Skeleton\Template\Template();
		$application = \Skeleton\Core\Application::Get();

		if (isset($application->template_path) and file_exists($application->template_path)) {
			$this->template->add_template_path($application->template_path);
		}

		$skeletons = \Skeleton\Core\Skeleton::get_all();

		foreach ($skeletons as $skeleton) {
			if (file_exists($skeleton->template_path)) {
				$this->template->add_template_path($skeleton->template_path, $skeleton->name);
			}
		}
	}

	/**
	 * Assign a variable to the template
	 *
	 * @access public
	 */
	public function assign(string $key, mixed $value): void {
		$this->template->assign($key, $value);
	}

	/**
	 * Add a variable to the environment
	 *
	 * @access public
	 */
	public function add_environment(string $key, mixed $value): void {
		$this->template->add_environment($key, $value);
	}

	/**
	 * Set translation
	 *
	 * @access public
	 * @param Translation $translation
	 */
	public function set_translation(\Skeleton\I18n\Translation $translation): void {
		$this->template->set_translation($translation);
	}

	/**
	 * Add template directory
	 *
	 * @access public
	 */
	public function add_template_directory(string $path, ?string $namespace = null, bool $prepend = false): void {
		/**
		 * @Deprecated: for backwards compatibility
		 */
		$this->template->add_template_path($path, $namespace, $prepend);
	}

	/**
	 * Add template directory
	 *
	 * @access public
	 */
	public function add_template_path(string $path, ?string $namespace = null, bool $prepend = false): void {
		$this->template->add_template_path($path, $namespace, $prepend);
	}

	/**
	 * Display a template
	 *
	 * @access public
	 */
	public function display(string $template, bool $rewrite_html = true): void {
		echo $this->render($template, $rewrite_html);
	}

	/**
	 * Render a template
	 *
	 * @access public
	 * @return string $rendered_template
	 */
	public function render(string $template): string {
		$csrf = Security\Csrf::get();
		$this->add_environment('csrf_session_token_name', $csrf->get_session_token_name());
		$this->add_environment('csrf_header_token_name', $csrf->get_header_token_name());
		$this->add_environment('csrf_post_token_name', $csrf->get_post_token_name());
		$this->add_environment('csrf_token', $csrf->get_session_token());

		$replay = Security\Replay::get();
		$this->add_environment('replay_session_tokens_name', $replay->get_session_tokens_name());
		$this->add_environment('replay_header_token_name', $replay->get_header_token_name());
		$this->add_environment('replay_post_token_name', $replay->get_post_token_name());

		$output = $this->template->render($template);
		$output = $csrf->inject($output);
		$output = $replay->inject($output);

		// Reverse rewrite the html
		$application = \Skeleton\Core\Application::get();
		if ($application->event_exists('rewrite', 'reverse')) {
			$output = $application->call_event('rewrite', 'reverse', [$output]);
		}
		return $output;
	}

	/**
	 * Get function, returns Template object
	 */
	public static function get(): self {
		if (self::$web_template === null) {
			self::$web_template = new self();
		}

		return self::$web_template;
	}
}
