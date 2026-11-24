<?php
/**
 * PHPStan stub for Elementor's widget base.
 *
 * Elementor provides the real class when the builder is active. This file is
 * loaded only by PHPStan so the breadcrumbs widget can be analysed.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace Elementor;

if ( ! class_exists( Widget_Base::class, false ) ) {
	/**
	 * Elementor widget parent. Methods used by RankMySEO are declared here.
	 */
	class Widget_Base {
		/**
		 * Widget slug.
		 *
		 * @return string
		 */
		public function get_name(): string {
			return '';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title(): string {
			return '';
		}

		/**
		 * Icon class.
		 *
		 * @return string
		 */
		public function get_icon(): string {
			return '';
		}

		/**
		 * Categories.
		 *
		 * @return string[]
		 */
		public function get_categories(): array {
			return [];
		}

		/**
		 * Search keywords.
		 *
		 * @return string[]
		 */
		public function get_keywords(): array {
			return [];
		}

		/**
		 * Print the widget.
		 *
		 * @return void
		 */
		protected function render(): void {
		}
	}
}
