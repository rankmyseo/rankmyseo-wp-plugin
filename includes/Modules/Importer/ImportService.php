<?php
/**
 * Dry-run, chunked import, and snapshot rollback.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Copies mapped SEO data into RankMySEO and can restore the previous values.
 *
 * Source plugin meta and tables are never deleted or updated.
 */
final class ImportService {
	/**
	 * History entries kept on the site.
	 */
	private const HISTORY_LIMIT = 40;

	/**
	 * Objects inspected while building a dry-run conflict count.
	 */
	private const PREVIEW_SCAN_LIMIT = 250;

	/**
	 * Adapter factory.
	 *
	 * @var AdapterFactory
	 */
	private AdapterFactory $factory;

	/**
	 * Destination store.
	 *
	 * @var SiteStore
	 */
	private SiteStore $store;

	/**
	 * Constructor.
	 *
	 * @param AdapterFactory $factory Adapter factory.
	 * @param SiteStore      $store   Destination store.
	 */
	public function __construct( AdapterFactory $factory, SiteStore $store ) {
		$this->factory = $factory;
		$this->store   = $store;
	}

	/**
	 * Detect every supported source.
	 *
	 * @return array<string, mixed>
	 */
	public function detect_all(): array {
		$sources = [];

		foreach ( $this->factory->all() as $adapter ) {
			$sources[] = array_merge(
				[
					'id'    => $adapter->id(),
					'label' => $adapter->label(),
				],
				$adapter->detect()
			);
		}

		return [
			'sources'         => $sources,
			'nothing_deleted' => true,
		];
	}

	/**
	 * Count records and return a few before/after field mappings. Writes nothing.
	 *
	 * @param ImportRequest $request Preview options.
	 * @return array<string, mixed>
	 */
	public function preview( ImportRequest $request ): array {
		$adapter = $this->factory->match( $request->source );
		if ( null === $adapter ) {
			return $this->error( 'rankmyseo_unknown_source', __( 'Choose Yoast SEO, Rank Math, or All in One SEO.', 'rankmyseo' ), 400 );
		}

		$detected = $adapter->detect();
		$counts   = [
			'posts'     => $request->include_posts ? $adapter->count( 'posts' ) : 0,
			'terms'     => $request->include_terms ? $adapter->count( 'terms' ) : 0,
			'redirects' => $request->include_redirects ? $adapter->count( 'redirects' ) : 0,
			'settings'  => $request->include_settings ? $adapter->count( 'settings' ) : 0,
		];

		$samples   = [];
		$conflicts = 0;
		$seen      = 0;

		foreach ( $request->phases() as $phase ) {
			$offset = 0;
			while ( $seen < self::PREVIEW_SCAN_LIMIT ) {
				$slice   = $adapter->slice( $phase, $offset, 40 );
				$scanned = (int) $slice['scanned'];
				if ( 0 === $scanned ) {
					break;
				}

				foreach ( $slice['items'] as $item ) {
					if ( ! $item instanceof WorkItem ) {
						continue;
					}
					$plan = $this->plan( $item, $request->overwrite );
					if ( $plan['conflict'] ) {
						++$conflicts;
					}
					foreach ( $plan['fields'] as $field ) {
						if ( count( $samples ) >= 5 ) {
							break;
						}
						$samples[] = $field;
					}
					++$seen;
				}

				$offset += $scanned;
				if ( $scanned < 40 ) {
					break;
				}
			}
		}

		return [
			'source'          => $adapter->id(),
			'label'           => $adapter->label(),
			'active'          => (bool) $detected['active'],
			'found'           => (bool) $detected['found'],
			'posts'           => $counts['posts'],
			'terms'           => $counts['terms'],
			'redirects'       => $counts['redirects'],
			'settings'        => $counts['settings'],
			'conflicts'       => $conflicts,
			'samples'         => $samples,
			'nothing_deleted' => true,
		];
	}

	/**
	 * Import one chunk and snapshot anything it changes.
	 *
	 * @param ImportRequest $request Batch options.
	 * @return array<string, mixed>
	 */
	public function run( ImportRequest $request ): array {
		$adapter = $this->factory->match( $request->source );
		if ( null === $adapter ) {
			return $this->error( 'rankmyseo_unknown_source', __( 'Choose Yoast SEO, Rank Math, or All in One SEO.', 'rankmyseo' ), 400 );
		}

		$import_id = strtolower( trim( $request->import_id ) );
		if ( '' === $import_id ) {
			$import_id = $this->uuid();
		} elseif ( ! $this->is_uuid( $import_id ) ) {
			return $this->error( 'rankmyseo_invalid_import', __( 'That import ID is not valid.', 'rankmyseo' ), 400 );
		}

		$batch  = max( 1, min( 200, $request->batch_size ) );
		$phases = $request->phases();
		if ( [] === $phases ) {
			$this->remember( $request, $import_id, $this->empty_totals(), true );
			return $this->batch_payload( $import_id, $adapter->id(), '', true, 0, 0, 0, '', $batch );
		}

		[ $phase, $offset ] = $this->parse_cursor( $request->cursor, $phases );
		$budget             = $batch;
		$processed          = 0;
		$written            = 0;
		$skipped            = 0;
		$totals             = $this->empty_totals();
		$guard              = 0;

		while ( '' !== $phase && $budget > 0 && $guard < 8 ) {
			++$guard;
			$requested = $budget;
			$slice     = $adapter->slice( $phase, $offset, $budget );
			$scanned   = (int) $slice['scanned'];

			foreach ( $slice['items'] as $item ) {
				if ( ! $item instanceof WorkItem ) {
					continue;
				}
				$plan      = $this->plan( $item, $request->overwrite );
				$committed = $this->commit( $plan['operations'], $import_id, $request->source );
				$written  += $committed['written'];
				$skipped  += $plan['skipped'];
				$totals    = $this->add_totals( $totals, $committed['objects'] );
			}

			$processed += $scanned;
			$offset    += $scanned;
			$budget    -= $scanned;

			if ( $scanned < $requested ) {
				$phase  = $this->next_phase( $phase, $phases );
				$offset = 0;
				continue;
			}

			break;
		}

		$done   = '' === $phase;
		$cursor = $done ? '' : $phase . ':' . $offset;
		$this->remember(
			$request,
			$import_id,
			[
				'written'   => $written,
				'skipped'   => $skipped,
				'posts'     => $totals['posts'],
				'terms'     => $totals['terms'],
				'redirects' => $totals['redirects'],
				'settings'  => $totals['settings'],
			],
			$done
		);

		return $this->batch_payload( $import_id, $adapter->id(), $cursor, $done, $processed, $written, $skipped, $done ? '' : $phase, $batch );
	}

	/**
	 * Restore RankMySEO data changed by one import and drop the snapshot.
	 *
	 * @param string $import_id Import ID.
	 * @return array<string, mixed>
	 */
	public function rollback( string $import_id ): array {
		$import_id = strtolower( trim( $import_id ) );
		if ( ! $this->is_uuid( $import_id ) ) {
			return $this->error( 'rankmyseo_invalid_import', __( 'That import ID is not valid.', 'rankmyseo' ), 400 );
		}

		$entries = $this->history_entries();
		$known   = false;
		$status  = '';
		foreach ( $entries as $entry ) {
			if ( ( $entry['import_id'] ?? '' ) === $import_id ) {
				$known  = true;
				$status = (string) ( $entry['status'] ?? '' );
				break;
			}
		}

		if ( 'rolled_back' === $status ) {
			return [
				'import_id'       => $import_id,
				'status'          => 'already_rolled_back',
				'restored'        => 0,
				'nothing_deleted' => true,
			];
		}

		$rows = $this->store->backups( $import_id );
		if ( ! $known && [] === $rows ) {
			return $this->error( 'rankmyseo_import_not_found', __( 'That import could not be found.', 'rankmyseo' ), 404 );
		}

		$restored = 0;
		foreach ( array_reverse( $rows ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$this->restore_row( $row );
			++$restored;
		}

		$this->store->delete_backups( $import_id );
		$this->mark_rolled_back( $import_id );

		return [
			'import_id'       => $import_id,
			'status'          => 'rolled_back',
			'restored'        => $restored,
			'nothing_deleted' => true,
		];
	}

	/**
	 * List past imports, newest first.
	 *
	 * @return array<string, mixed>
	 */
	public function history(): array {
		return [
			'imports' => $this->history_entries(),
		];
	}

	/**
	 * Decide which fields would be written or skipped.
	 *
	 * @param WorkItem $item      Mapped object.
	 * @param bool     $overwrite Whether existing values may be replaced.
	 * @return array{fields: array<int, array<string, mixed>>, operations: array<int, array<string, mixed>>, skipped: int, conflict: bool}
	 */
	private function plan( WorkItem $item, bool $overwrite ): array {
		if ( is_array( $item->redirect ) ) {
			return $this->plan_redirect( $item, $overwrite );
		}
		if ( is_array( $item->options ) ) {
			return $this->plan_options( $item, $overwrite );
		}
		return $this->plan_meta( $item, $overwrite );
	}

	/**
	 * Plan post or term meta writes.
	 *
	 * @param WorkItem $item      Mapped object.
	 * @param bool     $overwrite Whether existing values may be replaced.
	 * @return array{fields: array<int, array<string, mixed>>, operations: array<int, array<string, mixed>>, skipped: int, conflict: bool}
	 */
	private function plan_meta( WorkItem $item, bool $overwrite ): array {
		$fields     = [];
		$operations = [];
		$skipped    = 0;
		$labels     = MetaCatalog::field_labels();

		foreach ( $item->meta as $key => $value ) {
			$current = $this->store->get_meta( $item->object_type, $item->object_id, $key );
			if ( $current === $value ) {
				continue;
			}

			$action = 'write';
			if ( null !== $current && '' !== $current && ! $overwrite ) {
				$action = 'skip';
				++$skipped;
			} else {
				$operations[] = [
					'type'        => 'meta',
					'object_type' => $item->object_type,
					'object_id'   => $item->object_id,
					'key'         => $key,
					'value'       => $value,
					'previous'    => $current,
				];
			}

			$fields[] = $this->field_sample( $item, $key, $labels[ $key ] ?? $key, (string) $current, $value, $action );
		}

		return [
			'fields'     => $fields,
			'operations' => $operations,
			'skipped'    => $skipped,
			'conflict'   => $skipped > 0,
		];
	}

	/**
	 * Plan a redirect insert or update.
	 *
	 * @param WorkItem $item      Mapped redirect.
	 * @param bool     $overwrite Whether an existing rule may be replaced.
	 * @return array{fields: array<int, array<string, mixed>>, operations: array<int, array<string, mixed>>, skipped: int, conflict: bool}
	 */
	private function plan_redirect( WorkItem $item, bool $overwrite ): array {
		$row = $item->redirect;
		if ( ! is_array( $row ) ) {
			return $this->empty_plan();
		}

		$hash     = (string) ( $row['source_hash'] ?? '' );
		$existing = '' === $hash ? null : $this->store->find_redirect_by_hash( $hash );
		$after    = (string) ( $row['target_url'] ?? '' );
		$before   = is_array( $existing ) ? (string) ( $existing['target_url'] ?? '' ) : '';

		if ( is_array( $existing ) && $this->redirect_same( $existing, $row ) ) {
			return $this->empty_plan();
		}

		if ( is_array( $existing ) && ! $overwrite ) {
			return [
				'fields'     => [ $this->field_sample( $item, 'redirect', __( 'Redirect', 'rankmyseo' ), $before, $after, 'skip' ) ],
				'operations' => [],
				'skipped'    => 1,
				'conflict'   => true,
			];
		}

		$operation = [
			'type'     => is_array( $existing ) ? 'redirect_update' : 'redirect_insert',
			'row'      => $row,
			'previous' => $existing,
		];

		return [
			'fields'     => [ $this->field_sample( $item, 'redirect', __( 'Redirect', 'rankmyseo' ), $before, $after, 'write' ) ],
			'operations' => [ $operation ],
			'skipped'    => 0,
			'conflict'   => false,
		];
	}

	/**
	 * Plan option patches, replacing same_as as a whole list.
	 *
	 * @param WorkItem $item      Settings item.
	 * @param bool     $overwrite Whether existing values may be replaced.
	 * @return array{fields: array<int, array<string, mixed>>, operations: array<int, array<string, mixed>>, skipped: int, conflict: bool}
	 */
	private function plan_options( WorkItem $item, bool $overwrite ): array {
		$fields     = [];
		$operations = [];
		$skipped    = 0;

		foreach ( $item->options ?? [] as $key => $patch ) {
			if ( ! is_array( $patch ) ) {
				continue;
			}
			$current = $this->store->get_option( $key );
			$existed = is_array( $current );
			$base    = $existed ? $current : [];
			$leaves  = 0;
			$next    = $this->merge_node( $key, $base, $patch, $overwrite, '', $leaves, $skipped, $fields, $item );
			if ( $leaves > 0 ) {
				$operations[] = [
					'type'     => 'option',
					'key'      => $key,
					'next'     => $next,
					'previous' => $existed ? $current : null,
					'existed'  => $existed,
					'written'  => $leaves,
				];
			}
		}

		return [
			'fields'     => $fields,
			'operations' => $operations,
			'skipped'    => $skipped,
			'conflict'   => $skipped > 0,
		];
	}

	/**
	 * Merge one settings tree.
	 *
	 * @param string                           $option    Option name.
	 * @param array<string, mixed>             $current   Current tree.
	 * @param array<string, mixed>             $patch     Incoming tree.
	 * @param bool                             $overwrite Whether existing values may be replaced.
	 * @param string                           $path      Dot path of this node.
	 * @param int                              $written   Leaves that will change.
	 * @param int                              $skipped   Leaves left in place.
	 * @param array<int, array<string, mixed>> $fields Field samples.
	 * @param WorkItem                         $item      Source item.
	 * @return array<string, mixed>
	 */
	private function merge_node( string $option, array $current, array $patch, bool $overwrite, string $path, int &$written, int &$skipped, array &$fields, WorkItem $item ): array {
		foreach ( $patch as $key => $value ) {
			$child = '' === $path ? (string) $key : $path . '.' . $key;
			$now   = array_key_exists( (string) $key, $current ) ? $current[ $key ] : null;

			if ( is_array( $value ) && ! $this->is_list( $value ) ) {
				$base            = is_array( $now ) ? $now : [];
				$current[ $key ] = $this->merge_node( $option, $base, $value, $overwrite, $child, $written, $skipped, $fields, $item );
				continue;
			}

			if ( $this->values_equal( $now, $value ) ) {
				continue;
			}

			$action = 'write';
			if ( ! $overwrite && ! $this->is_blank( $now ) && ! $this->is_default( $option, $child, $now ) ) {
				$action = 'skip';
				++$skipped;
			} else {
				$current[ $key ] = $value;
				++$written;
			}

			$fields[] = $this->field_sample(
				$item,
				$option . '.' . $child,
				$child,
				$this->display_value( $now ),
				$this->display_value( $value ),
				$action
			);
		}

		return $current;
	}

	/**
	 * Apply planned writes and store the previous RankMySEO values.
	 *
	 * @param array<int, array<string, mixed>> $operations Planned writes.
	 * @param string                           $import_id  Import ID.
	 * @param string                           $source     Source ID.
	 * @return array{written: int, objects: array{posts: int, terms: int, redirects: int, settings: int}}
	 */
	private function commit( array $operations, string $import_id, string $source ): array {
		$written   = 0;
		$posts     = [];
		$terms     = [];
		$redirects = 0;
		$settings  = 0;
		$groups    = [];

		foreach ( $operations as $operation ) {
			if ( 'meta' !== ( $operation['type'] ?? '' ) ) {
				continue;
			}
			$group                    = $operation['object_type'] . ':' . $operation['object_id'];
			$groups[ $group ]['type'] = (string) $operation['object_type'];
			$groups[ $group ]['id']   = (int) $operation['object_id'];
			$groups[ $group ]['keys'][ (string) $operation['key'] ] = $operation['previous'];
		}

		foreach ( $groups as $group ) {
			$this->store->insert_backup(
				$import_id,
				$source,
				(string) $group['type'],
				(int) $group['id'],
				[
					'kind' => 'meta',
					'keys' => $group['keys'],
				]
			);
			if ( 'post' === $group['type'] ) {
				$posts[ (int) $group['id'] ] = true;
			}
			if ( 'term' === $group['type'] ) {
				$terms[ (int) $group['id'] ] = true;
			}
		}

		foreach ( $operations as $operation ) {
			$type = (string) ( $operation['type'] ?? '' );
			if ( 'meta' === $type ) {
				$this->store->set_meta(
					(string) $operation['object_type'],
					(int) $operation['object_id'],
					(string) $operation['key'],
					(string) $operation['value']
				);
				++$written;
				continue;
			}

			if ( 'redirect_insert' === $type && is_array( $operation['row'] ) ) {
				$id = $this->store->insert_redirect( $operation['row'] );
				$this->store->insert_backup(
					$import_id,
					$source,
					'redirect',
					$id,
					[
						'kind'    => 'redirect',
						'created' => true,
						'id'      => $id,
					]
				);
				++$written;
				++$redirects;
				continue;
			}

			if ( 'redirect_update' === $type && is_array( $operation['row'] ) && is_array( $operation['previous'] ) ) {
				$previous           = $operation['previous'];
				$row                = $operation['row'];
				$id                 = (int) ( $previous['id'] ?? 0 );
				$row['id']          = $id;
				$row['hits']        = (int) ( $previous['hits'] ?? 0 );
				$row['created_at']  = $previous['created_at'] ?? $row['created_at'];
				$row['last_hit_at'] = $previous['last_hit_at'] ?? null;
				$this->store->insert_backup(
					$import_id,
					$source,
					'redirect',
					$id,
					[
						'kind'     => 'redirect',
						'created'  => false,
						'id'       => $id,
						'previous' => $previous,
					]
				);
				$this->store->update_redirect( $row );
				++$written;
				++$redirects;
				continue;
			}

			if ( 'option' === $type ) {
				$this->store->insert_backup(
					$import_id,
					$source,
					'option',
					0,
					[
						'kind'    => 'option',
						'key'     => (string) $operation['key'],
						'existed' => (bool) $operation['existed'],
						'value'   => $operation['previous'],
					]
				);
				$this->store->set_option( (string) $operation['key'], $operation['next'] );
				$written += (int) $operation['written'];
				$settings = 1;
			}
		}

		return [
			'written' => $written,
			'objects' => [
				'posts'     => count( $posts ),
				'terms'     => count( $terms ),
				'redirects' => $redirects,
				'settings'  => $settings,
			],
		];
	}

	/**
	 * Put one snapshot back.
	 *
	 * @param array<string, mixed> $previous Snapshot payload.
	 * @return void
	 */
	private function restore( array $previous ): void {
		$kind = (string) ( $previous['kind'] ?? '' );

		if ( 'redirect' === $kind && ! empty( $previous['created'] ) ) {
			$this->store->delete_redirect( (int) ( $previous['id'] ?? 0 ) );
			return;
		}

		if ( 'redirect' === $kind && isset( $previous['previous'] ) && is_array( $previous['previous'] ) ) {
			$this->store->update_redirect( $previous['previous'] );
			return;
		}

		if ( 'option' === $kind ) {
			$key = (string) ( $previous['key'] ?? '' );
			if ( '' === $key ) {
				return;
			}
			if ( empty( $previous['existed'] ) ) {
				$this->store->delete_option( $key );
				return;
			}
			$this->store->set_option( $key, $previous['value'] );
		}
	}

	/**
	 * Restore meta after the early return above is replaced.
	 *
	 * Meta restore is handled in restore_meta() so object identity stays with the backup row.
	 *
	 * @param array<string, mixed> $backup Backup row from the store.
	 * @return void
	 */
	private function restore_row( array $backup ): void {
		$previous = $backup['previous'] ?? null;
		if ( ! is_array( $previous ) ) {
			return;
		}

		if ( 'meta' === ( $previous['kind'] ?? '' ) && isset( $previous['keys'] ) && is_array( $previous['keys'] ) ) {
			$object_type = (string) ( $backup['object_type'] ?? '' );
			$object_id   = (int) ( $backup['object_id'] ?? 0 );
			foreach ( $previous['keys'] as $key => $value ) {
				if ( null === $value ) {
					$this->store->delete_meta( $object_type, $object_id, (string) $key );
					continue;
				}
				$this->store->set_meta( $object_type, $object_id, (string) $key, (string) $value );
			}
			return;
		}

		$this->restore( $previous );
	}

	/**
	 * Compare the columns that define a redirect rule.
	 *
	 * @param array<string, mixed> $existing Stored row.
	 * @param array<string, mixed> $row      Incoming row.
	 * @return bool
	 */
	private function redirect_same( array $existing, array $row ): bool {
		$target = $existing['target_url'] ?? null;
		if ( '' === $target ) {
			$target = null;
		}

		return (string) ( $existing['source_hash'] ?? '' ) === (string) ( $row['source_hash'] ?? '' )
			&& (string) ( $existing['match_type'] ?? '' ) === (string) ( $row['match_type'] ?? '' )
			&& (int) ( $existing['status_code'] ?? 0 ) === (int) ( $row['status_code'] ?? 0 )
			&& (int) ( $existing['is_active'] ?? 0 ) === (int) ( $row['is_active'] ?? 0 )
			&& ( $row['target_url'] ?? null ) === $target;
	}

	/**
	 * Whether a settings value is empty enough to replace.
	 *
	 * @param mixed $value Current value.
	 * @return bool
	 */
	private function is_blank( $value ): bool {
		return null === $value || '' === $value || [] === $value;
	}

	/**
	 * Whether a stored value is still the plugin default.
	 *
	 * @param string $option Option name.
	 * @param string $path   Dot path.
	 * @param mixed  $value  Current value.
	 * @return bool
	 */
	private function is_default( string $option, string $path, $value ): bool {
		if ( MetaCatalog::OPTION_CORE === $option && 'title_separator' === $path && '|' === $value ) {
			return true;
		}
		if ( MetaCatalog::OPTION_CORE === $option && 'site_type' === $path && 'organization' === $value ) {
			return true;
		}
		if ( MetaCatalog::OPTION_SCHEMA === $option && 'entity_type' === $path && 'organization' === $value ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether an array is a list rather than a map.
	 *
	 * @param array<mixed> $value Value to test.
	 * @return bool
	 */
	private function is_list( array $value ): bool {
		if ( [] === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Compare scalars and lists without merging list indexes.
	 *
	 * @param mixed $left  Current value.
	 * @param mixed $right Incoming value.
	 * @return bool
	 */
	private function values_equal( $left, $right ): bool {
		if ( is_array( $left ) || is_array( $right ) ) {
			return wp_json_encode( $left ) === wp_json_encode( $right );
		}
		return $left === $right;
	}

	/**
	 * Render a value for the preview table.
	 *
	 * @param mixed $value Stored or incoming value.
	 * @return string
	 */
	private function display_value( $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_array( $value ) ) {
			$encoded = wp_json_encode( $value );
			return is_string( $encoded ) ? $encoded : '';
		}
		return (string) $value;
	}

	/**
	 * Build one preview row.
	 *
	 * @param WorkItem $item   Source item.
	 * @param string   $field  Field key.
	 * @param string   $label  Field label.
	 * @param string   $before Current value.
	 * @param string   $after  Incoming value.
	 * @param string   $action write or skip.
	 * @return array<string, mixed>
	 */
	private function field_sample( WorkItem $item, string $field, string $label, string $before, string $after, string $action ): array {
		return [
			'object_type' => $item->object_type,
			'object_id'   => $item->object_id,
			'label'       => $item->label,
			'field'       => $field,
			'field_label' => $label,
			'before'      => $before,
			'after'       => $after,
			'action'      => $action,
			'unsupported' => $item->unsupported,
		];
	}

	/**
	 * Empty plan.
	 *
	 * @return array{fields: array<int, array<string, mixed>>, operations: array<int, array<string, mixed>>, skipped: int, conflict: bool}
	 */
	private function empty_plan(): array {
		return [
			'fields'     => [],
			'operations' => [],
			'skipped'    => 0,
			'conflict'   => false,
		];
	}

	/**
	 * Zeroed object counters.
	 *
	 * @return array{posts: int, terms: int, redirects: int, settings: int}
	 */
	private function empty_totals(): array {
		return [
			'posts'     => 0,
			'terms'     => 0,
			'redirects' => 0,
			'settings'  => 0,
		];
	}

	/**
	 * Add object counters.
	 *
	 * @param array{posts: int, terms: int, redirects: int, settings: int} $left  Running totals.
	 * @param array{posts: int, terms: int, redirects: int, settings: int} $right Batch totals.
	 * @return array{posts: int, terms: int, redirects: int, settings: int}
	 */
	private function add_totals( array $left, array $right ): array {
		return [
			'posts'     => $left['posts'] + $right['posts'],
			'terms'     => $left['terms'] + $right['terms'],
			'redirects' => $left['redirects'] + $right['redirects'],
			'settings'  => min( 1, $left['settings'] + $right['settings'] ),
		];
	}

	/**
	 * Read the cursor against the enabled phases.
	 *
	 * @param string             $cursor Cursor string.
	 * @param array<int, string> $phases Enabled phases.
	 * @return array{0: string, 1: int}
	 */
	private function parse_cursor( string $cursor, array $phases ): array {
		$cursor = trim( $cursor );
		if ( '' === $cursor || ! preg_match( '/^(settings|posts|terms|redirects):(\d+)$/', $cursor, $matches ) ) {
			return [ $phases[0], 0 ];
		}

		$phase = (string) $matches[1];
		if ( ! in_array( $phase, $phases, true ) ) {
			return [ $phases[0], 0 ];
		}

		return [ $phase, (int) $matches[2] ];
	}

	/**
	 * Next enabled phase, or an empty string when the import is finished.
	 *
	 * @param string             $phase  Current phase.
	 * @param array<int, string> $phases Enabled phases.
	 * @return string
	 */
	private function next_phase( string $phase, array $phases ): string {
		$index = array_search( $phase, $phases, true );
		if ( false === $index ) {
			return '';
		}
		$next = $index + 1;
		return $phases[ $next ] ?? '';
	}

	/**
	 * Create or update the history row for this import.
	 *
	 * @param ImportRequest      $request   Request.
	 * @param string             $import_id Import ID.
	 * @param array<string, int> $delta     Counts for this batch.
	 * @param bool               $done      Whether the import finished.
	 * @return void
	 */
	private function remember( ImportRequest $request, string $import_id, array $delta, bool $done ): void {
		$entries = $this->history_entries();
		$now     = gmdate( 'Y-m-d H:i:s' );
		$found   = false;

		foreach ( $entries as $index => $entry ) {
			if ( ( $entry['import_id'] ?? '' ) !== $import_id ) {
				continue;
			}
			$entry['written']   = (int) ( $entry['written'] ?? 0 ) + (int) ( $delta['written'] ?? 0 );
			$entry['skipped']   = (int) ( $entry['skipped'] ?? 0 ) + (int) ( $delta['skipped'] ?? 0 );
			$entry['posts']     = (int) ( $entry['posts'] ?? 0 ) + (int) ( $delta['posts'] ?? 0 );
			$entry['terms']     = (int) ( $entry['terms'] ?? 0 ) + (int) ( $delta['terms'] ?? 0 );
			$entry['redirects'] = (int) ( $entry['redirects'] ?? 0 ) + (int) ( $delta['redirects'] ?? 0 );
			$entry['settings']  = min( 1, (int) ( $entry['settings'] ?? 0 ) + (int) ( $delta['settings'] ?? 0 ) );
			$entry['overwrite'] = $request->overwrite;
			if ( $done && 'rolled_back' !== ( $entry['status'] ?? '' ) ) {
				$entry['status']      = 'completed';
				$entry['finished_at'] = $now;
			}
			$entries[ $index ] = $entry;
			$found             = true;
			break;
		}

		if ( ! $found ) {
			array_unshift(
				$entries,
				[
					'import_id'   => $import_id,
					'source'      => $request->source,
					'status'      => $done ? 'completed' : 'running',
					'started_at'  => $now,
					'finished_at' => $done ? $now : '',
					'posts'       => (int) ( $delta['posts'] ?? 0 ),
					'terms'       => (int) ( $delta['terms'] ?? 0 ),
					'redirects'   => (int) ( $delta['redirects'] ?? 0 ),
					'settings'    => (int) ( $delta['settings'] ?? 0 ),
					'written'     => (int) ( $delta['written'] ?? 0 ),
					'skipped'     => (int) ( $delta['skipped'] ?? 0 ),
					'overwrite'   => $request->overwrite,
				]
			);
		}

		$this->store->set_option( MetaCatalog::OPTION_HISTORY, array_slice( $entries, 0, self::HISTORY_LIMIT ) );
	}

	/**
	 * Mark an import rolled back.
	 *
	 * @param string $import_id Import ID.
	 * @return void
	 */
	private function mark_rolled_back( string $import_id ): void {
		$entries = $this->history_entries();
		foreach ( $entries as $index => $entry ) {
			if ( ( $entry['import_id'] ?? '' ) !== $import_id ) {
				continue;
			}
			$entry['status']         = 'rolled_back';
			$entry['rolled_back_at'] = gmdate( 'Y-m-d H:i:s' );
			$entries[ $index ]       = $entry;
		}
		$this->store->set_option( MetaCatalog::OPTION_HISTORY, $entries );
	}

	/**
	 * Read history entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function history_entries(): array {
		$stored = $this->store->get_option( MetaCatalog::OPTION_HISTORY );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$entries = [];
		foreach ( $stored as $entry ) {
			if ( is_array( $entry ) ) {
				$entries[] = $entry;
			}
		}
		return $entries;
	}

	/**
	 * Batch response shared by a finished and an in-progress chunk.
	 *
	 * @param string $import_id Import ID.
	 * @param string $source    Source ID.
	 * @param string $cursor    Next cursor.
	 * @param bool   $done      Whether the import finished.
	 * @param int    $processed Source rows read.
	 * @param int    $written   Values written.
	 * @param int    $skipped   Values left in place.
	 * @param string $phase     Current phase when more work remains.
	 * @param int    $batch     Applied batch size.
	 * @return array<string, mixed>
	 */
	private function batch_payload( string $import_id, string $source, string $cursor, bool $done, int $processed, int $written, int $skipped, string $phase, int $batch ): array {
		return [
			'import_id'       => $import_id,
			'source'          => $source,
			'cursor'          => $cursor,
			'next_cursor'     => $cursor,
			'done'            => $done,
			'processed'       => $processed,
			'written'         => $written,
			'skipped'         => $skipped,
			'phase'           => $phase,
			'batch_size'      => $batch,
			'nothing_deleted' => true,
		];
	}

	/**
	 * Error payload the controller turns into WP_Error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return array<string, mixed>
	 */
	private function error( string $code, string $message, int $status ): array {
		return [
			'error' => [
				'code'    => $code,
				'message' => $message,
				'status'  => $status,
			],
		];
	}

	/**
	 * Whether a string is a UUID.
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	private function is_uuid( string $value ): bool {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value );
	}

	/**
	 * UUID v4 that does not depend on a WordPress helper.
	 *
	 * @return string
	 */
	private function uuid(): string {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );

		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20, 12 )
		);
	}
}
