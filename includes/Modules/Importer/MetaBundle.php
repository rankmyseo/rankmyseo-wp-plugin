<?php
/**
 * Builds the RankMySEO meta map from a normalized source record.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Shared field encoding for every source adapter.
 */
final class MetaBundle {
	/**
	 * Template translator.
	 *
	 * @var TemplateTranslator
	 */
	private TemplateTranslator $translator;

	/**
	 * Constructor.
	 *
	 * @param TemplateTranslator $translator Token translator.
	 */
	public function __construct( TemplateTranslator $translator ) {
		$this->translator = $translator;
	}

	/**
	 * Turn a normalized record into meta values and unsupported tokens.
	 *
	 * @param array<string, mixed> $record Normalized source fields.
	 * @return array{meta: array<string, string>, unsupported: array<int, string>}
	 */
	public function build( array $record ): array {
		$unsupported = [];
		$meta        = [];

		$this->put_text( $meta, $unsupported, MetaCatalog::TITLE, $record['title'] ?? '', MetaCatalog::TITLE_MAX );
		$this->put_text( $meta, $unsupported, MetaCatalog::DESCRIPTION, $record['description'] ?? '', MetaCatalog::DESCRIPTION_MAX );
		$this->put_text( $meta, $unsupported, MetaCatalog::OG_TITLE, $record['og_title'] ?? '', MetaCatalog::TITLE_MAX );
		$this->put_text( $meta, $unsupported, MetaCatalog::OG_DESCRIPTION, $record['og_description'] ?? '', MetaCatalog::DESCRIPTION_MAX );
		$this->put_text( $meta, $unsupported, MetaCatalog::TWITTER_TITLE, $record['twitter_title'] ?? '', MetaCatalog::TITLE_MAX );
		$this->put_text( $meta, $unsupported, MetaCatalog::TWITTER_DESCRIPTION, $record['twitter_description'] ?? '', MetaCatalog::DESCRIPTION_MAX );

		$canonical = PlainText::url_or_attachment( $record['canonical'] ?? '' );
		if ( '' !== $canonical && ! ctype_digit( $canonical ) ) {
			$meta[ MetaCatalog::CANONICAL ] = $canonical;
		}

		$focus = PlainText::line( $record['focus'] ?? '', MetaCatalog::KEYWORD_MAX );
		if ( '' !== $focus ) {
			$meta[ MetaCatalog::FOCUS_KEYWORD ] = $focus;
		}

		$secondary = $this->keywords( $record['secondary'] ?? [] );
		if ( [] !== $secondary ) {
			$encoded = wp_json_encode( $secondary );
			if ( is_string( $encoded ) ) {
				$meta[ MetaCatalog::SECONDARY_KEYWORDS ] = $encoded;
			}
		}

		$image = PlainText::url_or_attachment( $record['og_image'] ?? '' );
		if ( '' !== $image ) {
			$meta[ MetaCatalog::OG_IMAGE ] = $image;
		}

		$twitter_image = PlainText::url_or_attachment( $record['twitter_image'] ?? '' );
		if ( '' !== $twitter_image ) {
			$meta[ MetaCatalog::TWITTER_IMAGE ] = $twitter_image;
		}

		$card = sanitize_key( (string) ( $record['twitter_card'] ?? '' ) );
		if ( in_array( $card, [ 'summary', 'summary_large_image' ], true ) ) {
			$meta[ MetaCatalog::TWITTER_CARD ] = $card;
		}

		$robots = $this->robots( $record['robots'] ?? [] );
		if ( '' !== $robots ) {
			$meta[ MetaCatalog::ROBOTS ] = $robots;
		}

		return [
			'meta'        => $meta,
			'unsupported' => array_values( array_unique( $unsupported ) ),
		];
	}

	/**
	 * Translate and store one text field when it is not empty.
	 *
	 * @param array<string, string> $meta         Destination map.
	 * @param array<int, string>    $unsupported  Unsupported tokens.
	 * @param string                $key          Meta key.
	 * @param mixed                 $value        Raw value.
	 * @param int                   $max          Maximum length.
	 * @return void
	 */
	private function put_text( array &$meta, array &$unsupported, string $key, $value, int $max ): void {
		$raw = PlainText::line( $value, $max + 200 );
		if ( '' === $raw ) {
			return;
		}

		$translated = $this->translator->translate( $raw );
		$text       = PlainText::line( $translated['text'], $max );
		if ( '' === $text ) {
			return;
		}

		$meta[ $key ] = $text;
		$unsupported  = array_merge( $unsupported, $translated['unsupported'] );
	}

	/**
	 * Sanitize a secondary keyword list.
	 *
	 * @param mixed $value Raw keywords.
	 * @return array<int, string>
	 */
	private function keywords( $value ): array {
		$value = PlainText::decode_structured( $value );
		if ( is_string( $value ) ) {
			$parts = preg_split( '/\s*,\s*/', $value );
			$value = is_array( $parts ) ? $parts : [];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$clean = [];
		foreach ( $value as $keyword ) {
			if ( is_array( $keyword ) ) {
				$keyword = $keyword['keyphrase'] ?? $keyword['keyword'] ?? '';
			}
			$text = PlainText::line( $keyword, MetaCatalog::KEYWORD_MAX );
			if ( '' === $text || in_array( $text, $clean, true ) ) {
				continue;
			}
			$clean[] = $text;
			if ( count( $clean ) >= MetaCatalog::SECONDARY_MAX ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Encode robots flags in the titles-module JSON shape.
	 *
	 * @param mixed $value Flag map.
	 * @return string Empty when every flag is off.
	 */
	private function robots( $value ): string {
		$value = PlainText::decode_structured( $value );
		if ( ! is_array( $value ) ) {
			return '';
		}

		$flags = [
			'noindex'      => false,
			'nofollow'     => false,
			'noarchive'    => false,
			'nosnippet'    => false,
			'noimageindex' => false,
		];

		foreach ( $flags as $flag => $unused ) {
			unset( $unused );
			if ( array_key_exists( $flag, $value ) && PlainText::is_truthy( $value[ $flag ] ) ) {
				$flags[ $flag ] = true;
			}
			if ( in_array( $flag, $value, true ) ) {
				$flags[ $flag ] = true;
			}
		}

		if ( ! in_array( true, $flags, true ) ) {
			return '';
		}

		$payload = [
			'noindex'           => $flags['noindex'],
			'nofollow'          => $flags['nofollow'],
			'noarchive'         => $flags['noarchive'],
			'nosnippet'         => $flags['nosnippet'],
			'noimageindex'      => $flags['noimageindex'],
			'max_snippet'       => -1,
			'max_image_preview' => '',
			'max_video_preview' => -1,
		];

		$encoded = wp_json_encode( $payload );
		return is_string( $encoded ) ? $encoded : '';
	}
}
