<?php
/**
 * @file
 */

namespace MediaWiki\Extension\Diagrams;

use DOMDocument;
use DOMXPath;
use Title;

/**
 * This class represents an HTML image-map, modifying it where required to support MediaWiki
 * features such as links.
 */
class ImageMap {

	/** @var string The map contents as an HTML string. */
	protected $map;

	/** @var DOMDocument The map contents as a DomDocument. */
	protected $domDocument;

	/**
	 * @param string $mapHtml HTML string of the <map> element.
	 */
	public function __construct( $mapHtml ) {
		$this->map = $mapHtml;
	}

	/**
	 * Get the imagemap HTML.
	 *
	 * @return string
	 */
	public function getMap() {
		$dom = $this->getDomDocument();
		$dom->formatOutput = false;
		return $dom->saveXML( $dom->firstChild );
	}

	/**
	 * Whether or not this imagemap has any area elements within it (i.e. if it's an actual imagemap).
	 *
	 * @return bool
	 */
	public function hasAreas() {
		return $this->getDomDocument()->getElementsByTagName( 'area' )->count() > 0;
	}

	/**
	 * Get the imagemap's element name, for use in the associated img element.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->getDomDocument()->documentElement->getAttribute( 'name' );
	}

	/**
	 * @return DOMDocument
	 */
	protected function getDomDocument() {
		if ( $this->domDocument ) {
			return $this->domDocument;
		}

		// Load its DOM.
		$dom = new DOMDocument();
		$oldValue = libxml_disable_entity_loader( true );
		$dom->loadXML( $this->map );
		libxml_disable_entity_loader( $oldValue );

		// Set a more specific ID and name (the name is referenced by the image element).
		$id = 'ext-diagrams-' . $dom->documentElement->getAttribute( 'id' );
		$dom->documentElement->setAttribute( 'id', $id );
		$dom->documentElement->setAttribute( 'name', $id );

		// Convert links to actual URLs.
		$xpath = new DOMXPath( $dom );
		$hrefNodes = $xpath->query( '//*[@href]' );
		foreach ( $hrefNodes as $hrefNode ) {
			$newHref = preg_replace_callback( '/(\[\[?)([^\]\|]+)\|?([^\]])*\]?\]/', function ( $matches ) {
				if ( $matches[1] === '[[' ) {
					// Internal link.
					$title = Title::newFromText( $matches[2] );
					if ( $title instanceof Title ) {
						$out = $title->getLinkURL();
					}
				} elseif ( $matches[1] === '[' ) {
					// Remove the brackets from an external link and return it untouched.
					$out = $matches[2];
				}
				return $out;
			}, $hrefNode->getAttribute( 'href' ) );
			$hrefNode->setAttribute( 'href', $newHref );
		}
		$this->domDocument = $dom;
		return $this->domDocument;
	}
}
