<?php

declare(strict_types=1);

/**
 * Module Context
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Application\Web\Event;

class Module extends \Skeleton\Core\Application\Event {
	/**
	 * Teardown
	 *
	 * @access public
	 * @param \Skeleton\Application\Web\Module
	 */
	public function teardown(\Skeleton\Application\Web\Module $module): void {
		// No default action
	}
	/**
	 * Bootstrap
	 *
	 * @access public
	 * @param \Skeleton\Application\Web\Module
	 */
	public function bootstrap(\Skeleton\Application\Web\Module $module): void {
		// No default action
	}

	/**
	 * Access denied
	 *
	 * @access public
	 * @param \Skeleton\Core\Application\Web\Module
	 */
	public function access_denied(\Skeleton\Application\Web\Module $module): void {
		throw new \Exception('Access denied');
	}

	/**
	 * Media not found
	 *
	 * @access public
	 */
	public function not_found(): void {
		\Skeleton\Core\Http\Status::code_404('module');
	}
}
