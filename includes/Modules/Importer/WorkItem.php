<?php
/**
 * One object ready to copy into RankMySEO.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Mapped post, term, redirect, or settings payload.
 */
final class WorkItem {
	/**
	 * Object type: post, term, redirect, or option.
	 *
	 * @var string
	 */
	public string $object_type;

	/**
	 * WordPress object ID. Zero for site settings.
	 *
	 * @var int
	 */
	public int $object_id;

	/**
	 * Human label used in the preview table.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * Meta key to stored string value.
	 *
	 * @var array<string, string>
	 */
	public array $meta;

	/**
	 * Redirect columns without an ID, or null.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $redirect;

	/**
	 * Option key to a partial settings patch.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	public ?array $options;

	/**
	 * Source variables that were left unchanged.
	 *
	 * @var array<int, string>
	 */
	public array $unsupported;

	/**
	 * Constructor.
	 *
	 * @param string                                   $object_type  Object type.
	 * @param int                                      $object_id    Object ID.
	 * @param string                                   $label        Preview label.
	 * @param array<string, string>                    $meta         Meta values.
	 * @param array<string, mixed>|null                $redirect     Redirect row.
	 * @param array<string, array<string, mixed>>|null $options      Option patches.
	 * @param array<int, string>                       $unsupported  Unmapped variables.
	 */
	public function __construct(
		string $object_type,
		int $object_id,
		string $label,
		array $meta = [],
		?array $redirect = null,
		?array $options = null,
		array $unsupported = []
	) {
		$this->object_type = $object_type;
		$this->object_id   = $object_id;
		$this->label       = $label;
		$this->meta        = $meta;
		$this->redirect    = $redirect;
		$this->options     = $options;
		$this->unsupported = $unsupported;
	}

	/**
	 * Whether the item would change RankMySEO data.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		if ( [] !== $this->meta ) {
			return false;
		}
		if ( is_array( $this->redirect ) ) {
			return false;
		}
		return null === $this->options || [] === $this->options;
	}
}
