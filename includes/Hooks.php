<?php

namespace MediaWiki\Extension\Diagrams;

use Html;
use Http;
use MediaWiki\MediaWikiServices;
use Parser;

class Hooks {

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		foreach ( [ 'graphviz', 'mscgen', 'uml' ] as $tag ) {
			$parser->setHook( $tag, function ( string $input ) use ( $tag ) {
				// Make sure there's something to render.
				$input = trim( $input );
				if ( $input === '' ) {
					return '';
				}
				if ( $tag === 'graphviz' ) {
					// GraphViz.
					return static::render( $tag, $input, 'cmapx' );
				} elseif ( $tag === 'mscgen' ) {
					// Mscgen.
					return static::render( $tag, $input, 'ismap' );
				} else {
					// PlantUML.
					return static::render( 'plantuml', $input );
				}
			} );
		}
	}

	/**
	 * Get HTML for an error message.
	 * @param string $error Error message. May contain HTML.
	 * @return string
	 */
	protected static function formatError( $error ) {
		return Html::rawElement( 'span', [ 'class' => 'ext-diagrams error' ], $error );
	}

	/**
	 * The main rendering method, handling all types.
	 * @param string $generator
	 * @param string $input
	 * @param string|null $type
	 * @return string
	 */
	protected static function render( $generator, $input, $type = null ) {
		$baseUrl = MediaWikiServices::getInstance()->getMainConfig()->get( 'DiagramsServiceUrl' );
		$url = trim( $baseUrl, '/' ) . '/render';
		$params = [
			'postData' => http_build_query( [
				'generator' => $generator,
				'types' => array_filter( [ 'png', $type ] ),
				'source' => $input,
			] ),
		];
		$result = Http::request( 'POST', $url, $params, __METHOD__ );
		if ( $result === false ) {
			return static::formatError( wfMessage( 'diagrams-error-no-response' ) );
		}
		$response = json_decode( $result );
		if ( isset( $response->error ) ) {
			$error = wfMessage( 'diagrams-error-returned-' . $response->error );
			if ( isset( $response->message ) ) {
				$error .= Html::element( 'br' ) . $response->message;
			}
			return static::formatError( $error );
		}
		$imgAttrs = [ 'src' => $response->diagrams->png->url ];
		if ( isset( $response->diagrams->cmapx->contents ) ) {
			// Image maps in cmapx format.
			$imageMap = new ImageMap( $response->diagrams->cmapx->contents );
			if ( $imageMap->hasAreas() ) {
				$imgAttrs['usemap'] = '#' . $imageMap->getName();
			}
			$out = Html::element( 'img', $imgAttrs );
			if ( $imageMap->hasAreas() ) {
				$out .= $imageMap->getMap();
			}
		} elseif ( isset( $response->diagrams->ismap->contents ) ) {
			// Image maps in imap format.
			$imgAttrs['ismap'] = true;
			$out = Html::rawElement(
				'a',
				[ 'href' => $response->diagrams->ismap->url ],
				Html::element( 'img', $imgAttrs )
			);
		} else {
			// No image map.
			$out = Html::element( 'img', $imgAttrs );
		}
		return Html::rawElement( 'div', [ 'class' => 'ext-diagrams' ], $out );
	}
}
