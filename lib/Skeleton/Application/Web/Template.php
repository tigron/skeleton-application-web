<?php
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
	 *
	 * @var int $unique
	 */
	private $unique = 0;

	/**
	 * Template
	 *
	 * @access private
	 * @var Template $template
	 */
	private $template = null;

	/**
	 * Template
	 *
	 * @var Web_Template $template
	 * @access private
	 */
	private static $web_template = null;

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
	 * @param string $key
	 * @param mixed $value
	 */
	public function assign($key, $value) {
		$this->template->assign($key, $value);
	}

	/**
	 * Add a variable to the environment
	 *
	 * @access public
	 * @param string $key
	 * @param mixed $value
	 */
	public function add_environment($key, $value) {
		$this->template->add_environment($key, $value);
	}

	/**
	 * Set translation
	 *
	 * @access public
	 * @param Translation $translation
	 */
	public function set_translation(\Skeleton\I18n\Translation $translation) {
		$this->template->set_translation($translation);
	}

	/**
	 * Add template directory
	 *
	 * @access public
	 * @param string $path
	 * @param string $namespace
	 * @param bool $prepend
	 */
	public function add_template_directory($path, $namespace = null, $prepend = false) {
		/**
		 * @Deprecated: for backwards compatibility
		 */
		$this->template->add_template_path($path, $namespace, $prepend);
	}

	/**
	 * Add template directory
	 *
	 * @access public
	 * @param string $path
	 * @param string $namespace
	 * @param bool $prepend
	 */
	public function add_template_path($path, $namespace = null, $prepend = false) {
		$this->template->add_template_path($path, $namespace, $prepend);
	}

	/**
	 * Display a template
	 *
	 * @access public
	 * @param string $template
	 * @param bool $rewrite_html
	 */
	public function display($template, $rewrite_html = true) {
		echo $this->render($template, $rewrite_html);
	}

	/**
	 * Render a template
	 *
	 * @access public
	 * @param string $template
	 * @param bool $rewrite_html
	 * @return string $rendered_template
	 */
	public function render($template, $rewrite_html = true) {
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

		if ($rewrite_html) {
			return \Skeleton\Core\Util::rewrite_reverse_html($output);
		} else {
			return $output;
		}
	}

	/**
	 * Get function, returns Template object
	 */
	public static function get() {
		if (self::$web_template === null) {
			self::$web_template = new Template();
		}

		return self::$web_template;
	}
}
