<?php

declare(strict_types=1);

/**
 * Automated replay handling
 *
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Application\Web\Security;

class Replay {
	/**
	 * Local replay instance
	 *
	 * @access private
	 */
	private static ?self $replay = null;

	/**
	 * Session tokens name
	 *
	 * @access private
	 */
	private string $session_tokens_name = '__replay-tokens';

	/**
	 * POST token name
	 *
	 * @access private
	 */
	private string $post_token_name = '__replay-token';

	/**
	 * Header token name
	 *
	 * @access private
	 */
	private string $header_token_name = 'x-replay-token';

	/**
	 * Is replay enabled for the current request
	 *
	 * @access private
	 */
	private bool $enabled = true;

	/**
	 * Constructor
	 *
	 * @access private
	 */
	private function __construct() {
		$application = \Skeleton\Core\Application::get();

		if (isset($application->config->replay_session_tokens_name)) {
			$this->session_tokens_name = $application->config->replay_session_tokens_name;
		}

		if (isset($application->config->replay_header_token_name)) {
			$this->sheader_token_name = $application->config->replay_header_token_name;
		}

		if (isset($application->config->replay_post_token_name)) {
			$this->post_token_name = $application->config->replay_post_token_name;
		}

		// Disable the replay check if the configuration tells us to
		if (isset($application->config->replay_enabled) and $application->config->replay_enabled === false) {
			$this->enabled = false;
		}
	}

	/**
	 * Create a static instance
	 *
	 * @return Replay
	 * @access public
	 */
	public static function get(): self {
		if (!isset(self::$replay)) {
			self::$replay = new self();
		}

		return self::$replay;
	}

	/**
	 * Get the current replay session token name
	 *
	 * @access public
	 * @return ?string $replay_session_token_name
	 */
	public function get_session_tokens_name(): ?string {
		return $this->session_tokens_name;
	}

	/**
	 * Get the current replay header token name
	 *
	 * @access public
	 * @return ?string $replay_header_token_name
	 */
	public function get_header_token_name(): ?string {
		return $this->header_token_name;
	}

	/**
	 * Get the current replay post token name
	 *
	 * @access public
	 * @return ?string $replay_post_token_name
	 */
	public function get_post_token_name(): ?string {
		return $this->post_token_name;
	}

	/**
	 * Inject a replay token form element into rendered HTML
	 *
	 * @access public
	 */
	public function inject(string $html): string {
		if ($this->enabled === false) {
			return $html;
		}

		$application = \Skeleton\Core\Application::get();

		return $application->call_event('security', 'replay_inject', [
			$html,
			$this->get_post_token_name(),
			bin2hex(random_bytes(25)),
		]);
	}

	/**
	 * Validate
	 *
	 * @access public
	 */
	public function check(): bool {
		// If replay checking is not enabled, don't do anything
		if ($this->enabled === false) {
			return true;
		}

		// Save the token locally so we can unset it later, as to not hinder
		// further processing
		if (isset($_POST[$this->post_token_name])) {
			$submitted_token = $_POST[$this->post_token_name];
		} elseif (isset($_SERVER[strtoupper('http_' . str_replace('-', '_', $this->header_token_name))])) {
			$submitted_token = $_SERVER[strtoupper('http_' . str_replace('-', '_', $this->header_token_name))];
		} else {
			$submitted_token = null;
		}

		unset($_POST[$this->post_token_name]);

		if (!empty($_POST)) {
			if (
				!isset($_SESSION[$this->get_session_tokens_name()]) ||
				!is_array($_SESSION[$this->get_session_tokens_name()])
			) {
				// Initialise the token array if we don't have one yet
				$_SESSION[$this->get_session_tokens_name()] = [];
			} else {
				// Clean up expired tokens
				foreach ($_SESSION[$this->get_session_tokens_name()] as $key => $token) {
					if (strpos($key, '_') === false) {
						unset($_SESSION[$this->get_session_tokens_name()][$key]);
						continue;
					}

					// Previous tokens are kept for 30 seconds
					$time = substr($key, 0, strpos($key, '_'));
					if (time() - $time >= 30) {
						unset($_SESSION[$this->get_session_tokens_name()][$key]);
					}
				}
			}

			if (!empty($submitted_token)) {
				if (in_array($submitted_token, $_SESSION[$this->get_session_tokens_name()])) {
					return false;
				}
				$_SESSION[$this->get_session_tokens_name()][uniqid(time() . '_')] = $submitted_token;
				return true;
			}
		}

		return true;
	}
}
