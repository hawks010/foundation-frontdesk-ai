<?php
/**
 * GitHub-backed updater bootstrap for Foundation: Frontdesk AI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Foundation_Frontdesk_Github_Updater {
	/**
	 * Singleton instance.
	 *
	 * @var Foundation_Frontdesk_Github_Updater|null
	 */
	private static $instance = null;

	/**
	 * Underlying update checker instance.
	 *
	 * @var object|null
	 */
	private $checker = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Foundation_Frontdesk_Github_Updater
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the updater bootstrap.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 5 );
	}

	/**
	 * Boot the bundled plugin-update-checker library.
	 *
	 * @return void
	 */
	public function boot() {
		if ( null !== $this->checker ) {
			return;
		}

		$loader = FND_CONVERSA_PATH . 'plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $loader ) ) {
			return;
		}

		require_once $loader;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		try {
			$this->checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				'https://github.com/' . $this->get_repository(),
				FND_CONVERSA_FILE,
				$this->get_plugin_slug()
			);
		} catch ( \Exception $exception ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Foundation Frontdesk updater error: ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Get the configured repository slug.
	 *
	 * @return string
	 */
	private function get_repository() {
		$repository = apply_filters( 'foundation_frontdesk_github_repository', 'hawks010/foundation-frontdesk-ai' );

		return is_string( $repository ) ? trim( $repository ) : 'hawks010/foundation-frontdesk-ai';
	}

	/**
	 * Get the plugin slug.
	 *
	 * @return string
	 */
	private function get_plugin_slug() {
		return dirname( plugin_basename( FND_CONVERSA_FILE ) );
	}
}
