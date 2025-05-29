<?php

declare(strict_types=1);

/**
 * Security Context
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Application\Web\Event;

class Security extends \Skeleton\Core\Application\Event {
	/**
	 * Generate session token
	 *
	 * @access public
	 * @return string $session_token
	 */
	public function csrf_generate_session_token(): string {
		return bin2hex(random_bytes(32));
	}

	/**
	 * Is CSRF enabled
	 *
	 * @access public
	 * @return bool $enabled
	 */
	public function csrf_validate_enabled(): bool {
		return true;
	}

	/**
	 * Validate CSRF
	 *
	 * @access public
	 * @return bool $validated
	 */
	public function csrf_validate(?string $submitted_token, string $session_token): bool {
		// We only validate POST requests
		// This is probably not the most complete implementation, but let's agree that GET requests should never modify data and we're mostly covered
		if (getenv('REQUEST_METHOD') === 'POST') {
			if (empty($submitted_token) || $session_token !== $submitted_token) {
				return $this->application->call_event('security', 'csrf_validate_failed');
			}

			return $this->application->call_event('security', 'csrf_validate_success');
		}
		return true;
	}

	/**
	 * csrf_validate_failed
	 *
	 * The csrf_validate_failed method allows you to override the check
	 * result after a failed validation. It expects a boolean as a return value.
	 *
	 * @return bool $validated
	 */
	public function csrf_validate_failed(): bool {
		\Skeleton\Core\Http\Status::code_403('CSRF validation failed');
		return false;
	}

	/**
	 * csrf_validate_success
	 *
	 * The csrf_validate_success method allows you to override the check result
	 * after a successful validation. It expects a boolean as a return value.
	 *
	 * @return bool $validated
	 */
	public function csrf_validate_success(): bool {
		return true;
	}

	/**
	 * csrf_inject
	 *
	 * The csrf_inject method allows you to override the automatic injection of
	 * the hidden CSRF token elements in the HTML forms of the rendered
	 * template.
	 *
	 * @access public
	 * @return string $html
	 */
	public function csrf_inject(string $html, string $post_token_name, string $post_token): string {
		return preg_replace_callback(
			'/<form\s.*>/siU',
			static function($matches) use ($post_token_name, $post_token) {
				return sprintf(
					"%s\n<input name=\"%s\" type=\"hidden\" value=\"%s\" />\n",
					$matches[0], $post_token_name, $post_token
				);
			},
			$html
		);
	}

	/**
	 * replay_inject
	 *
	 * The csrf_inject method allows you to override the automatic injection of
	 * the hidden CSRF token elements in the HTML forms of the rendered
	 * template.
	 *
	 * @access public
	 * @return string $html
	 */
	public function replay_inject(string $html, string $post_token_name, string $post_token): string {
		return preg_replace_callback(
			'/<form\s.*>/iU',
			static function($matches) use ($post_token_name, $post_token) {
				return sprintf(
					"%s\n<input name=\"%s\" type=\"hidden\" value=\"%s\" />\n",
					$matches[0], $post_token_name, $post_token
				);
			},
			$html
		);
	}

	/**
	 * replay_detected
	 *
	 * The replay_detected method allows you to catch replay detection events.
	 * By default, the user is redirected to the value of the HTTP referrer
	 * header if it is present
	 *
	 * @access public
	 */
	public function replay_detected(): void {
		if (!empty($_SERVER['HTTP_REFERER'])) {
			\Skeleton\Core\Http\Session::redirect($_SERVER['HTTP_REFERER'], false);
		} else {
			\Skeleton\Core\Http\Session::redirect('/');
		}
	}

	/**
	 * session_cookie
	 *
	 * The session_cookie method allows you to set session cookie parameters
	 * before the session is started. Typically, this would be used to SameSite
	 * cookie attribute.
	 *
	 * @access public
	 */
	public function session_cookie(): void {
		// No default action
	}
}
