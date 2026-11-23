<?php
/**
 * CSV import and export with spreadsheet formula-injection escaping.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Reads and writes redirect CSV payloads.
 */
final class CsvCodec {
	/**
	 * Characters that spreadsheet apps treat as the start of a formula.
	 *
	 * @var list<string>
	 */
	private const FORMULA_TRIGGERS = [ '=', '+', '-', '@', "\t", "\r" ];

	/**
	 * Column headers written by the exporter.
	 *
	 * @var list<string>
	 */
	public const HEADERS = [
		'source_path',
		'match_type',
		'target_url',
		'status_code',
		'is_active',
		'source_origin',
		'hits',
	];

	/**
	 * Prefix formula-like cells so spreadsheets store them as text.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public function escape_cell( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$first = $value[0];
		if ( in_array( $first, self::FORMULA_TRIGGERS, true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Build a CSV document from redirect rows.
	 *
	 * @param list<array<string, mixed>> $rows Redirect rows.
	 * @return string
	 */
	public function export( array $rows ): string {
		$file = new \SplTempFileObject();
		$file->fputcsv( self::HEADERS );

		foreach ( $rows as $row ) {
			$target = $row['target_url'] ?? '';
			$file->fputcsv(
				[
					$this->escape_cell( (string) ( $row['source_path'] ?? '' ) ),
					$this->escape_cell( (string) ( $row['match_type'] ?? 'exact' ) ),
					$this->escape_cell( is_string( $target ) ? $target : '' ),
					(string) (int) ( $row['status_code'] ?? 301 ),
					! empty( $row['is_active'] ) ? '1' : '0',
					$this->escape_cell( (string) ( $row['source_origin'] ?? 'manual' ) ),
					(string) (int) ( $row['hits'] ?? 0 ),
				]
			);
		}

		$file->rewind();
		$csv = '';
		foreach ( $file as $line ) {
			if ( is_string( $line ) ) {
				$csv .= $line;
			}
		}

		return $csv;
	}

	/**
	 * Parse a CSV document into associative rows.
	 *
	 * A single leading apostrophe added by escape_cell() is removed on import
	 * so a file exported from this plugin round-trips.
	 *
	 * @param string $csv Raw CSV text.
	 * @return list<array<string, string>>
	 */
	public function parse( string $csv ): array {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv ) ?? $csv;
		if ( strlen( $csv ) > 1048576 ) {
			$csv = substr( $csv, 0, 1048576 );
		}

		$lines = preg_split( '/\r\n|\n|\r/', $csv );
		if ( ! is_array( $lines ) ) {
			return [];
		}

		$rows    = [];
		$headers = null;

		foreach ( $lines as $line ) {
			if ( ! is_string( $line ) || '' === trim( $line ) ) {
				continue;
			}

			$cells = str_getcsv( $line );
			if ( ! is_array( $cells ) ) {
				continue;
			}

			if ( null === $headers ) {
				$headers = [];
				foreach ( $cells as $index => $header ) {
					$key = strtolower( trim( (string) $header ) );
					$key = $this->alias_header( $key );
					if ( '' !== $key ) {
						$headers[ $index ] = $key;
					}
				}
				continue;
			}

			$record = [];
			foreach ( $headers as $index => $key ) {
				$value          = isset( $cells[ $index ] ) ? (string) $cells[ $index ] : '';
				$record[ $key ] = $this->unescape_cell( $value );
			}

			if ( isset( $record['source_path'] ) && '' !== trim( $record['source_path'] ) ) {
				$rows[] = $record;
			}

			if ( count( $rows ) >= 2000 ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Map friendly header names onto canonical columns.
	 *
	 * @param string $header Lowercase header cell.
	 * @return string
	 */
	private function alias_header( string $header ): string {
		$map = [
			'source'      => 'source_path',
			'from'        => 'source_path',
			'source_url'  => 'source_path',
			'target'      => 'target_url',
			'to'          => 'target_url',
			'destination' => 'target_url',
			'code'        => 'status_code',
			'status'      => 'status_code',
			'type'        => 'match_type',
			'active'      => 'is_active',
			'origin'      => 'source_origin',
		];

		if ( isset( $map[ $header ] ) ) {
			return $map[ $header ];
		}

		if ( in_array( $header, self::HEADERS, true ) ) {
			return $header;
		}

		return '';
	}

	/**
	 * Remove one protective apostrophe added during export.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private function unescape_cell( string $value ): string {
		if ( strlen( $value ) < 2 || "'" !== $value[0] ) {
			return $value;
		}

		$second = $value[1];
		if ( in_array( $second, self::FORMULA_TRIGGERS, true ) ) {
			return substr( $value, 1 );
		}

		return $value;
	}
}
