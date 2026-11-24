<?php
/**
 * Unified Schema.org JSON-LD @graph builder.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Assembles one @graph document and exposes each piece through PHP filters.
 */
final class GraphBuilder {
	/**
	 * Build a JSON-LD document for the given context.
	 *
	 * @param SchemaContext $context Page and site data.
	 * @return array<string, mixed>
	 */
	public function build( SchemaContext $context ): array {
		if ( is_array( $context->custom_graph ) ) {
			$pieces = $context->custom_graph;
		} else {
			$pieces = $this->generated_pieces( $context );
		}

		$filtered = apply_filters( 'rankmyseo_schema_graph', $pieces, $context );
		if ( ! is_array( $filtered ) ) {
			$filtered = $pieces;
		}

		$graph = [];
		foreach ( $filtered as $piece ) {
			if ( is_array( $piece ) && [] !== $piece ) {
				$graph[] = $piece;
			}
		}

		return [
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		];
	}

	/**
	 * Generate the built-in pieces for a page.
	 *
	 * @param SchemaContext $context Page and site data.
	 * @return list<array<string, mixed>>
	 */
	private function generated_pieces( SchemaContext $context ): array {
		$pieces = [];

		$identity = $this->filter_piece( $this->identity_fragment( $context ), $this->identity_piece( $context ), $context );
		if ( [] !== $identity ) {
			$pieces[] = $identity;
		}

		if ( $this->needs_author_node( $context ) ) {
			$author = $this->filter_piece( 'person', $this->author_piece( $context ), $context );
			if ( [] !== $author ) {
				$pieces[] = $author;
			}
		}

		$website = $this->filter_piece( 'website', $this->website_piece( $context ), $context );
		if ( [] !== $website ) {
			$pieces[] = $website;
		}

		$webpage = $this->filter_piece( 'webpage', $this->webpage_piece( $context ), $context );
		if ( [] !== $webpage ) {
			$pieces[] = $webpage;
		}

		$breadcrumb = $this->filter_piece( 'breadcrumb', $this->breadcrumb_piece( $context ), $context );
		if ( [] !== $breadcrumb ) {
			$pieces[] = $breadcrumb;
		}

		if ( $this->is_article( $context ) ) {
			$article = $this->filter_piece( 'article', $this->article_piece( $context ), $context );
			if ( [] !== $article ) {
				$pieces[] = $article;
			}
		}

		if ( $this->is_product( $context ) ) {
			$product = $this->filter_piece( 'product', $this->product_piece( $context ), $context );
			if ( [] !== $product ) {
				$pieces[] = $product;
			}
		}

		if ( $this->include_faq( $context ) ) {
			$faq = $this->filter_piece( 'faq', $this->faq_piece( $context ), $context );
			if ( [] !== $faq ) {
				$pieces[] = $faq;
			}
		}

		if ( is_array( $context->local_business ) ) {
			$local = $this->filter_piece( 'localbusiness', $this->local_business_piece( $context ), $context );
			if ( [] !== $local ) {
				$pieces[] = $local;
			}
		}

		return $pieces;
	}

	/**
	 * Run a piece-specific filter. An empty array removes the piece.
	 *
	 * @param string               $type    Piece slug.
	 * @param array<string, mixed> $piece   Generated node.
	 * @param SchemaContext        $context Page context.
	 * @return array<string, mixed>
	 */
	private function filter_piece( string $type, array $piece, SchemaContext $context ): array {
		switch ( $type ) {
			case 'organization':
				$filtered = apply_filters( 'rankmyseo_schema_piece_organization', $piece, $context );
				break;
			case 'person':
				$filtered = apply_filters( 'rankmyseo_schema_piece_person', $piece, $context );
				break;
			case 'website':
				$filtered = apply_filters( 'rankmyseo_schema_piece_website', $piece, $context );
				break;
			case 'webpage':
				$filtered = apply_filters( 'rankmyseo_schema_piece_webpage', $piece, $context );
				break;
			case 'breadcrumb':
				$filtered = apply_filters( 'rankmyseo_schema_piece_breadcrumb', $piece, $context );
				break;
			case 'article':
				$filtered = apply_filters( 'rankmyseo_schema_piece_article', $piece, $context );
				break;
			case 'product':
				$filtered = apply_filters( 'rankmyseo_schema_piece_product', $piece, $context );
				break;
			case 'faq':
				$filtered = apply_filters( 'rankmyseo_schema_piece_faq', $piece, $context );
				break;
			case 'localbusiness':
				$filtered = apply_filters( 'rankmyseo_schema_piece_localbusiness', $piece, $context );
				break;
			default:
				$filtered = $piece;
				break;
		}

		return is_array( $filtered ) ? $filtered : $piece;
	}

	/**
	 * Identity fragment for the site publisher.
	 *
	 * @param SchemaContext $context Page context.
	 * @return string
	 */
	private function identity_fragment( SchemaContext $context ): string {
		return 'person' === $context->entity_type ? 'person' : 'organization';
	}

	/**
	 * Identifier of the site publisher.
	 *
	 * @param SchemaContext $context Page context.
	 * @return string
	 */
	private function identity_id( SchemaContext $context ): string {
		return SchemaIds::node_id( $context->home_url, $this->identity_fragment( $context ), $context->home_url );
	}

	/**
	 * Organization or Person publisher node.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function identity_piece( SchemaContext $context ): array {
		$is_person = 'person' === $context->entity_type;
		$piece     = [
			'@type' => $is_person ? 'Person' : 'Organization',
			'@id'   => $this->identity_id( $context ),
			'name'  => $this->text( $context->site_name, 120 ),
			'url'   => $this->site_url( $context ),
		];

		$piece = $this->with_text( $piece, 'description', $context->site_description, 500 );
		$piece = $this->with_text( $piece, 'telephone', $context->telephone, 40 );

		if ( '' !== $context->logo_url ) {
			$logo = [
				'@type'      => 'ImageObject',
				'@id'        => SchemaIds::node_id( $context->home_url, 'logo', $context->home_url ),
				'url'        => $context->logo_url,
				'contentUrl' => $context->logo_url,
				'caption'    => $this->text( $context->site_name, 120 ),
			];
			if ( $is_person ) {
				$piece['image'] = $logo;
			} else {
				$piece['logo'] = $logo;
			}
		}

		if ( [] !== $context->same_as ) {
			$piece['sameAs'] = array_values( $context->same_as );
		}

		return $piece;
	}

	/**
	 * Separate author Person when the byline is not the site identity.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function author_piece( SchemaContext $context ): array {
		return [
			'@type' => 'Person',
			'@id'   => SchemaIds::node_id( $context->permalink, 'author', $context->home_url ),
			'name'  => $this->text( $context->author_name, 120 ),
		];
	}

	/**
	 * WebSite node, optionally with a site search action.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function website_piece( SchemaContext $context ): array {
		$piece = [
			'@type'     => 'WebSite',
			'@id'       => SchemaIds::node_id( $context->home_url, 'website', $context->home_url ),
			'url'       => $this->site_url( $context ),
			'name'      => $this->text( $context->site_name, 120 ),
			'publisher' => [
				'@id' => $this->identity_id( $context ),
			],
		];

		$piece = $this->with_text( $piece, 'description', $context->site_description, 500 );
		if ( '' !== $context->language ) {
			$piece['inLanguage'] = $context->language;
		}

		if ( $context->search_enabled ) {
			$piece['potentialAction'] = [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => $this->site_url( $context ) . '?s={search_term_string}',
				],
				'query-input' => 'required name=search_term_string',
			];
		}

		return $piece;
	}

	/**
	 * WebPage node linked to the website, breadcrumb, and main entity.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function webpage_piece( SchemaContext $context ): array {
		$type  = in_array( $context->webpage_type, SchemaMeta::WEBPAGE_TYPES, true ) ? $context->webpage_type : 'WebPage';
		$piece = [
			'@type'      => $type,
			'@id'        => SchemaIds::node_id( $context->permalink, 'webpage', $context->home_url ),
			'url'        => $context->permalink,
			'name'       => $this->text( '' !== $context->title ? $context->title : $context->site_name, 110 ),
			'isPartOf'   => [
				'@id' => SchemaIds::node_id( $context->home_url, 'website', $context->home_url ),
			],
			'about'      => [
				'@id' => $this->identity_id( $context ),
			],
			'breadcrumb' => [
				'@id' => SchemaIds::node_id( $context->permalink, 'breadcrumb', $context->home_url ),
			],
		];

		$piece = $this->with_text( $piece, 'description', $context->description, 500 );
		$piece = $this->with_text( $piece, 'inLanguage', $context->language, 35 );
		$piece = $this->with_text( $piece, 'datePublished', $context->date_published, 40 );
		$piece = $this->with_text( $piece, 'dateModified', $context->date_modified, 40 );

		if ( '' !== $context->image_url ) {
			$piece['primaryImageOfPage'] = [
				'@type' => 'ImageObject',
				'url'   => $context->image_url,
			];
		}

		$main_entity = $this->main_entity_id( $context );
		if ( '' !== $main_entity ) {
			$piece['mainEntity'] = [
				'@id' => $main_entity,
			];
		}

		return $piece;
	}

	/**
	 * BreadcrumbList matching the supplied trail.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function breadcrumb_piece( SchemaContext $context ): array {
		$crumbs = $context->breadcrumbs;
		if ( [] === $crumbs ) {
			$crumbs = [
				[
					'name' => 'Home',
					'url'  => $this->site_url( $context ),
				],
			];
		}

		$items = [];
		foreach ( $crumbs as $crumb ) {
			if ( count( $items ) >= 10 ) {
				break;
			}
			$name = $this->text( (string) ( $crumb['name'] ?? '' ), 110 );
			$url  = (string) ( $crumb['url'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$item = [
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => $name,
			];
			if ( '' !== $url ) {
				$item['item'] = $url;
			}
			$items[] = $item;
		}

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => SchemaIds::node_id( $context->permalink, 'breadcrumb', $context->home_url ),
			'itemListElement' => $items,
		];
	}

	/**
	 * Article, BlogPosting, or NewsArticle node.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function article_piece( SchemaContext $context ): array {
		$type  = in_array( $context->schema_type, SchemaMeta::ARTICLE_TYPES, true ) ? $context->schema_type : 'Article';
		$piece = [
			'@type'            => $type,
			'@id'              => SchemaIds::node_id( $context->permalink, 'article', $context->home_url ),
			'headline'         => $this->text( $context->title, 110 ),
			'url'              => $context->permalink,
			'mainEntityOfPage' => [
				'@id' => SchemaIds::node_id( $context->permalink, 'webpage', $context->home_url ),
			],
			'author'           => [
				'@id' => $this->author_id( $context ),
			],
			'publisher'        => [
				'@id' => $this->identity_id( $context ),
			],
		];

		$piece = $this->with_text( $piece, 'description', $context->description, 500 );
		$piece = $this->with_text( $piece, 'datePublished', $context->date_published, 40 );
		$piece = $this->with_text( $piece, 'dateModified', $context->date_modified, 40 );
		$piece = $this->with_text( $piece, 'articleSection', $context->article_section, 80 );
		$piece = $this->with_text( $piece, 'inLanguage', $context->language, 35 );

		if ( $context->word_count > 0 ) {
			$piece['wordCount'] = $context->word_count;
		}

		if ( '' !== $context->image_url ) {
			$piece['image'] = [
				'@type' => 'ImageObject',
				'url'   => $context->image_url,
			];
		}

		return $piece;
	}

	/**
	 * Product node, including offers, identifiers, and reviews when present.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function product_piece( SchemaContext $context ): array {
		$product = is_array( $context->product ) ? $context->product : [];
		$name    = $this->text( (string) ( $product['name'] ?? $context->title ), 150 );
		$piece   = [
			'@type'            => 'Product',
			'@id'              => SchemaIds::node_id( $context->permalink, 'product', $context->home_url ),
			'name'             => $name,
			'url'              => $context->permalink,
			'mainEntityOfPage' => [
				'@id' => SchemaIds::node_id( $context->permalink, 'webpage', $context->home_url ),
			],
		];

		$description = (string) ( $product['description'] ?? $context->description );
		$piece       = $this->with_text( $piece, 'description', $description, 5000 );
		$piece       = $this->with_text( $piece, 'sku', (string) ( $product['sku'] ?? '' ), 70 );
		$piece       = $this->with_text( $piece, 'mpn', (string) ( $product['mpn'] ?? '' ), 70 );
		$piece       = $this->with_identifiers( $piece, (string) ( $product['gtin'] ?? '' ) );

		$image = (string) ( $product['image'] ?? $context->image_url );
		if ( '' !== $image ) {
			$piece['image'] = $image;
		}

		$brand = $this->text( (string) ( $product['brand'] ?? '' ), 100 );
		if ( '' !== $brand ) {
			$piece['brand'] = [
				'@type' => 'Brand',
				'name'  => $brand,
			];
		}

		$offer = $this->offer( $context, $product );
		if ( [] !== $offer ) {
			$piece['offers'] = $offer;
		}

		$rating_value = $this->text( (string) ( $product['rating_value'] ?? '' ), 8 );
		$review_count = (int) ( $product['review_count'] ?? 0 );
		if ( '' !== $rating_value && $review_count > 0 && is_numeric( $rating_value ) && (float) $rating_value > 0 ) {
			$piece['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating_value,
				'reviewCount' => $review_count,
			];
		}

		$reviews = $this->reviews( $product );
		if ( [] !== $reviews ) {
			$piece['review'] = $reviews;
		}

		return $piece;
	}

	/**
	 * Offer or AggregateOffer for a product snapshot.
	 *
	 * @param SchemaContext        $context Page context.
	 * @param array<string, mixed> $product Product snapshot.
	 * @return array<string, mixed>
	 */
	private function offer( SchemaContext $context, array $product ): array {
		$currency = strtoupper( (string) ( $product['currency'] ?? 'USD' ) );
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'USD';
		}

		$availability = (string) ( $product['availability'] ?? '' );
		if ( '' === $availability ) {
			$availability = 'https://schema.org/InStock';
		}

		$nested = $this->nested_offers( $context, $product, $currency );
		if ( count( $nested ) > 1 ) {
			$prices = [];
			foreach ( $nested as $index => $node ) {
				$nested[ $index ] = $this->with_merchant( $node, $product );
				$prices[]         = (float) $node['price'];
			}

			return $this->with_merchant(
				[
					'@type'         => 'AggregateOffer',
					'lowPrice'      => $this->price_string( min( $prices ) ),
					'highPrice'     => $this->price_string( max( $prices ) ),
					'offerCount'    => count( $nested ),
					'priceCurrency' => $currency,
					'availability'  => $this->summarize_offer_availability( $nested, $availability ),
					'url'           => $context->permalink,
					'offers'        => $nested,
				],
				$product
			);
		}
		if ( 1 === count( $nested ) ) {
			return $this->with_merchant( $nested[0], $product );
		}

		$low   = $this->price_string( $product['low_price'] ?? '' );
		$high  = $this->price_string( $product['high_price'] ?? '' );
		$count = (int) ( $product['offer_count'] ?? 0 );
		if ( '' !== $low && '' !== $high && $count > 1 ) {
			return $this->with_merchant(
				[
					'@type'         => 'AggregateOffer',
					'lowPrice'      => $low,
					'highPrice'     => $high,
					'offerCount'    => $count,
					'priceCurrency' => $currency,
					'availability'  => $availability,
					'url'           => $context->permalink,
				],
				$product
			);
		}

		$price = $this->price_string( $product['price'] ?? '' );
		if ( '' === $price ) {
			return [];
		}

		$offer = [
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => $currency,
			'availability'  => $availability,
			'url'           => $context->permalink,
		];
		$offer = $this->with_offer_identifiers( $offer, $product );

		return $this->with_merchant( $offer, $product );
	}

	/**
	 * Offer nodes for each priced variation.
	 *
	 * @param SchemaContext        $context  Page context.
	 * @param array<string, mixed> $product  Product snapshot.
	 * @param string               $currency ISO currency.
	 * @return list<array<string, mixed>>
	 */
	private function nested_offers( SchemaContext $context, array $product, string $currency ): array {
		$raw = $product['variations'] ?? [];
		if ( ! is_array( $raw ) || [] === $raw ) {
			return [];
		}

		$offers = [];
		foreach ( $raw as $row ) {
			if ( count( $offers ) >= 50 || ! is_array( $row ) ) {
				continue;
			}
			$price = $this->price_string( $row['price'] ?? '' );
			if ( '' === $price ) {
				continue;
			}
			$url          = $this->text( (string) ( $row['url'] ?? '' ), 500 );
			$availability = (string) ( $row['availability'] ?? '' );
			if ( '' === $availability ) {
				$availability = (string) ( $product['availability'] ?? 'https://schema.org/InStock' );
			}
			$offers[] = $this->with_offer_identifiers(
				[
					'@type'         => 'Offer',
					'price'         => $price,
					'priceCurrency' => $currency,
					'availability'  => $availability,
					'url'           => '' !== $url ? $url : $context->permalink,
				],
				$row
			);
		}

		return $offers;
	}

	/**
	 * Copy sku, gtin, isbn, and mpn onto an offer.
	 *
	 * @param array<string, mixed> $offer  Offer node.
	 * @param array<string, mixed> $source Identifier source.
	 * @return array<string, mixed>
	 */
	private function with_offer_identifiers( array $offer, array $source ): array {
		$sku = $this->text( (string) ( $source['sku'] ?? '' ), 70 );
		if ( '' !== $sku ) {
			$offer['sku'] = $sku;
		}
		$mpn = $this->text( (string) ( $source['mpn'] ?? '' ), 70 );
		if ( '' !== $mpn ) {
			$offer['mpn'] = $mpn;
		}

		return $this->with_identifiers( $offer, (string) ( $source['gtin'] ?? '' ) );
	}

	/**
	 * Add gtin and isbn when the identifier has a recognized length.
	 *
	 * @param array<string, mixed> $node  Schema node.
	 * @param string               $raw   Raw identifier.
	 * @return array<string, mixed>
	 */
	private function with_identifiers( array $node, string $raw ): array {
		$clean = strtoupper( str_replace( [ ' ', '-' ], '', trim( $raw ) ) );
		if ( 1 === preg_match( '/^\d{9}[\dX]$/', $clean ) ) {
			$node['isbn'] = $clean;
		}

		$digits = preg_replace( '/\D+/', '', $clean ) ?? '';
		if ( in_array( strlen( $digits ), [ 8, 12, 13, 14 ], true ) ) {
			$node['gtin'] = $digits;
		}
		if ( 13 === strlen( $digits ) && ( str_starts_with( $digits, '978' ) || str_starts_with( $digits, '979' ) ) ) {
			$node['isbn'] = $digits;
		}

		return $node;
	}

	/**
	 * Attach return and shipping details when the snapshot already built them.
	 *
	 * @param array<string, mixed> $offer   Offer node.
	 * @param array<string, mixed> $product Product snapshot.
	 * @return array<string, mixed>
	 */
	private function with_merchant( array $offer, array $product ): array {
		$return_policy = $product['return_policy'] ?? null;
		if ( is_array( $return_policy ) && isset( $return_policy['@type'] ) ) {
			$offer['hasMerchantReturnPolicy'] = $return_policy;
		}

		$shipping = $product['shipping_details'] ?? null;
		if ( is_array( $shipping ) && isset( $shipping['@type'] ) ) {
			$offer['shippingDetails'] = $shipping;
		}

		return $offer;
	}

	/**
	 * Availability for a group of offers. OutOfStock is checked before InStock.
	 *
	 * @param list<array<string, mixed>> $offers   Offer nodes.
	 * @param string                     $fallback Fallback availability.
	 * @return string
	 */
	private function summarize_offer_availability( array $offers, string $fallback ): string {
		$in_stock   = false;
		$back_order = false;
		foreach ( $offers as $offer ) {
			$value = (string) ( $offer['availability'] ?? '' );
			if ( str_contains( $value, 'OutOfStock' ) ) {
				continue;
			}
			if ( str_contains( $value, 'BackOrder' ) ) {
				$back_order = true;
				continue;
			}
			if ( str_contains( $value, 'InStock' ) ) {
				$in_stock = true;
			}
		}

		if ( $in_stock ) {
			return 'https://schema.org/InStock';
		}
		if ( $back_order ) {
			return 'https://schema.org/BackOrder';
		}

		return [] !== $offers ? 'https://schema.org/OutOfStock' : $fallback;
	}

	/**
	 * Review nodes from a product snapshot.
	 *
	 * @param array<string, mixed> $product Product snapshot.
	 * @return list<array<string, mixed>>
	 */
	private function reviews( array $product ): array {
		$raw = $product['reviews'] ?? [];
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$reviews = [];
		foreach ( $raw as $review ) {
			if ( count( $reviews ) >= 5 || ! is_array( $review ) ) {
				continue;
			}
			$body   = $this->text( (string) ( $review['body'] ?? '' ), 2000 );
			$author = $this->text( (string) ( $review['author'] ?? '' ), 120 );
			if ( '' === $body || '' === $author ) {
				continue;
			}
			$node = [
				'@type'      => 'Review',
				'reviewBody' => $body,
				'author'     => [
					'@type' => 'Person',
					'name'  => $author,
				],
			];
			$date = $this->text( (string) ( $review['date'] ?? '' ), 40 );
			if ( '' !== $date ) {
				$node['datePublished'] = $date;
			}
			$rating = $this->text( (string) ( $review['rating'] ?? '' ), 8 );
			if ( '' !== $rating && is_numeric( $rating ) ) {
				$node['reviewRating'] = [
					'@type'       => 'Rating',
					'ratingValue' => $rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				];
			}
			$reviews[] = $node;
		}

		return $reviews;
	}

	/**
	 * FAQPage node. Markup stays valid for answer engines after Google's May 2026 rich-result change.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function faq_piece( SchemaContext $context ): array {
		$questions = [];
		foreach ( $context->faqs as $faq ) {
			if ( count( $questions ) >= 50 ) {
				break;
			}
			$question = $this->text( (string) ( $faq['question'] ?? '' ), 300 );
			$answer   = $this->text( (string) ( $faq['answer'] ?? '' ), 5000 );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$questions[] = [
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $answer,
				],
			];
		}

		return [
			'@type'      => 'FAQPage',
			'@id'        => SchemaIds::node_id( $context->permalink, 'faq', $context->home_url ),
			'url'        => $context->permalink,
			'mainEntity' => $questions,
		];
	}

	/**
	 * LocalBusiness (or subtype) node.
	 *
	 * @param SchemaContext $context Page context.
	 * @return array<string, mixed>
	 */
	private function local_business_piece( SchemaContext $context ): array {
		$local = is_array( $context->local_business ) ? $context->local_business : [];
		$type  = (string) ( $local['type'] ?? 'LocalBusiness' );
		if ( ! in_array( $type, SchemaSettings::LOCAL_BUSINESS_TYPES, true ) ) {
			$type = 'LocalBusiness';
		}

		$name  = $this->text( (string) ( $local['name'] ?? '' ), 120 );
		$piece = [
			'@type' => $type,
			'@id'   => SchemaIds::node_id( $context->home_url, 'localbusiness', $context->home_url ),
			'name'  => '' !== $name ? $name : $this->text( $context->site_name, 120 ),
			'url'   => $this->site_url( $context ),
		];

		$piece = $this->with_text( $piece, 'telephone', (string) ( $local['telephone'] ?? '' ), 40 );
		$piece = $this->with_text( $piece, 'priceRange', (string) ( $local['price_range'] ?? '' ), 12 );
		$piece = $this->with_text( $piece, 'image', (string) ( $local['image'] ?? $context->logo_url ), 500 );

		$address = [
			'@type' => 'PostalAddress',
		];
		$map     = [
			'streetAddress'   => 'street',
			'addressLocality' => 'city',
			'addressRegion'   => 'region',
			'postalCode'      => 'postal_code',
			'addressCountry'  => 'country',
		];
		foreach ( $map as $schema_key => $source_key ) {
			$value = $this->text( (string) ( $local[ $source_key ] ?? '' ), 180 );
			if ( '' !== $value ) {
				$address[ $schema_key ] = $value;
			}
		}
		if ( count( $address ) > 1 ) {
			$piece['address'] = $address;
		}

		$latitude  = (string) ( $local['latitude'] ?? '' );
		$longitude = (string) ( $local['longitude'] ?? '' );
		if ( is_numeric( $latitude ) && is_numeric( $longitude ) ) {
			$piece['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => $latitude,
				'longitude' => $longitude,
			];
		}

		$hours = [];
		$rows  = $local['opening_hours'] ?? [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$day = (string) ( $row['day'] ?? '' );
				if ( ! in_array( $day, SchemaSettings::WEEKDAYS, true ) ) {
					continue;
				}
				$opens  = (string) ( $row['opens'] ?? '' );
				$closes = (string) ( $row['closes'] ?? '' );
				if ( '' === $opens || '' === $closes ) {
					continue;
				}
				$hours[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $day,
					'opens'     => $opens,
					'closes'    => $closes,
				];
			}
		}
		if ( [] !== $hours ) {
			$piece['openingHoursSpecification'] = $hours;
		}

		if ( 'organization' === $context->entity_type ) {
			$piece['parentOrganization'] = [
				'@id' => $this->identity_id( $context ),
			];
		}

		return $piece;
	}

	/**
	 * Whether a separate author node should be added.
	 *
	 * @param SchemaContext $context Page context.
	 * @return bool
	 */
	private function needs_author_node( SchemaContext $context ): bool {
		if ( ! $this->is_article( $context ) || '' === trim( $context->author_name ) ) {
			return false;
		}
		if ( 'person' === $context->entity_type ) {
			if ( $context->author_is_site_person || $context->author_name === $context->site_name ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Identifier referenced by Article.author.
	 *
	 * @param SchemaContext $context Page context.
	 * @return string
	 */
	private function author_id( SchemaContext $context ): string {
		if ( $this->needs_author_node( $context ) ) {
			return SchemaIds::node_id( $context->permalink, 'author', $context->home_url );
		}

		return $this->identity_id( $context );
	}

	/**
	 * Identifier used for WebPage.mainEntity.
	 *
	 * @param SchemaContext $context Page context.
	 * @return string
	 */
	private function main_entity_id( SchemaContext $context ): string {
		if ( $this->is_article( $context ) ) {
			return SchemaIds::node_id( $context->permalink, 'article', $context->home_url );
		}
		if ( $this->is_product( $context ) ) {
			return SchemaIds::node_id( $context->permalink, 'product', $context->home_url );
		}
		if ( 'FAQPage' === $context->schema_type && [] !== $context->faqs ) {
			return SchemaIds::node_id( $context->permalink, 'faq', $context->home_url );
		}
		if ( 'LocalBusiness' === $context->schema_type && is_array( $context->local_business ) ) {
			return SchemaIds::node_id( $context->home_url, 'localbusiness', $context->home_url );
		}

		return '';
	}

	/**
	 * Whether the page should include an article node.
	 *
	 * @param SchemaContext $context Page context.
	 * @return bool
	 */
	private function is_article( SchemaContext $context ): bool {
		return in_array( $context->schema_type, SchemaMeta::ARTICLE_TYPES, true );
	}

	/**
	 * Whether the page should include a product node.
	 *
	 * @param SchemaContext $context Page context.
	 * @return bool
	 */
	private function is_product( SchemaContext $context ): bool {
		return 'Product' === $context->schema_type;
	}

	/**
	 * Whether FAQ markup should be included.
	 *
	 * @param SchemaContext $context Page context.
	 * @return bool
	 */
	private function include_faq( SchemaContext $context ): bool {
		return 'none' !== $context->schema_type && [] !== $context->faqs;
	}

	/**
	 * Trailing-slashed site URL.
	 *
	 * @param SchemaContext $context Page context.
	 * @return string
	 */
	private function site_url( SchemaContext $context ): string {
		return trailingslashit( untrailingslashit( $context->home_url ) );
	}

	/**
	 * Trim text to a maximum length.
	 *
	 * @param string $value Raw text.
	 * @param int    $max   Maximum characters.
	 * @return string
	 */
	private function text( string $value, int $max ): string {
		$value = trim( $value );
		if ( mb_strlen( $value ) > $max ) {
			return mb_substr( $value, 0, $max );
		}

		return $value;
	}

	/**
	 * Add a text property when it is not empty.
	 *
	 * @param array<string, mixed> $piece Node.
	 * @param string               $key   Property name.
	 * @param string               $value Raw value.
	 * @param int                  $max   Maximum characters.
	 * @return array<string, mixed>
	 */
	private function with_text( array $piece, string $key, string $value, int $max ): array {
		$text = $this->text( $value, $max );
		if ( '' !== $text ) {
			$piece[ $key ] = $text;
		}

		return $piece;
	}

	/**
	 * Normalize a price to a two-decimal string.
	 *
	 * @param mixed $value Raw price.
	 * @return string
	 */
	private function price_string( $value ): string {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		if ( ! is_numeric( $value ) || (float) $value < 0 ) {
			return '';
		}

		return number_format( (float) $value, 2, '.', '' );
	}
}
