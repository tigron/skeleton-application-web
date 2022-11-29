<?php
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
	 * @param string $submitted_token
	 * @param string $session_token
	 * @return bool $validated
	 */
	public function csrf_validate($submitted_token, $session_token): bool {
		// We only validate POST requests
		// This is probably not the most complete implementation, but let's agree that GET requests should never modify data and we're mostly covered
		if (getenv('REQUEST_METHOD') === 'POST') {
			if (empty($submitted_token) || $session_token !== $submitted_token) {
				return $this->application->call_event('security', 'csrf_validate_failed');
			} else {
				return $this->application->call_event('security', 'csrf_validate_success');
			}
		} else {
			return true;
		}
	}

	/**
	 * csrf_validation_failed
	 *
	 * The csrf_validation_failed method allows you to override the check
	 * result after a failed validation. It expects a boolean as a return value.
	 *
	 * @return bool $validated
	 */
	public function csrf_validation_failed(): bool {
		\Skeleton\Core\Web\HTTP\Status::code_403('CSRF validation failed');
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
	 * @param string $html
	 * @param string $post_token_name
	 * @param string $post_token
	 * @return string $html
	 */
	public function csrf_inject($html, $post_token_name, $post_token): string {
		$html = preg_replace_callback(
			'/<form\s.*>/siU',
			function ($matches) {
				return sprintf("%s\n<input name=\"%s\" type=\"hidden\" value=\"%s\" />\n", $matches[0], $post_token_name, $post_token);
			},
			$html
		);
		return $html;
	}

	/**
	 * replay_inject
	 *
	 * The csrf_inject method allows you to override the automatic injection of
	 * the hidden CSRF token elements in the HTML forms of the rendered
	 * template.
	 *
	 * @access public
	 * @param string $html
	 * @param string $post_token_name
	 * @param string $post_token
	 * @return string $html
	 */
	public function replay_inject($html, $post_token_name, $post_token): string {
		$html = preg_replace_callback(
			'/<form\s.*>/iU',
			function ($matches) {
				return sprintf("%s\n<input name=\"%s\" type=\"hidden\" value=\"%s\" />\n", $matches[0], $this->post_token_name, bin2hex(random_bytes(25)));
			},
			$html
		);
		return $html;
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
	public function replay_detected() {
		if (!empty($_SERVER['HTTP_REFERER'])) {
		    Session::redirect($_SERVER['HTTP_REFERER'], false);
		} else {
		    Session::redirect('/');
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
	 * @return void
	 */
	public function session_cookie(): void {
		// No default action
	}



}
