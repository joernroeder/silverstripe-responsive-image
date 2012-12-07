<?php

/**
 * ResponsiveImage is an implementation of Scott Jehls "picturefill" 
 * @link https://github.com/scottjehl/picturefill
 *
 * @todo serve retina images
 *
 * @package ResponsiveImage
 */
class ResponsiveImage extends DataObject {

	// ! Config

	public static $responsive_breakpoints = array(
		'320' => 'small',
		'640' => 'medium',
		'888' => 'large',
		'1200' => 'extralarge',
		'1680' => 'panorama',
	);

	/**
	 * adds ie<=8 support
	 *
	 * @todo
	 * @static
	 * @link https://github.com/scottjehl/picturefill#supporting-ie-desktop
	 */
	public static $support_ie_desktop = true;

	/**
	 * sets the min-width for IE desktops
	 *
	 * @static
	 */
	public static $ie_desktop_min_width = 640;


	/**
	 * Fallback content for non-JS browsers.
	 *
	 * @static
	 */
	public static $add_noscript = true;

	/**
	 * Tag names for wrapper and image element
	 *
	 * @static
	 */
	public static $elements = array(
		'wrapper'	=> 'div',
		'image'		=> 'div'
	);


	// ! Statics

	static $db = array(
		'Title' => 'Varchar'
	);

	static $has_many = array(
		'Images' => 'ResponsiveImageObject'
	);

	static $summary_fields = array(
		'Thumbnail',
		'Title'
	);

	static $searchable_fields = array(
		'Title',
	);

	public static function get_responsive_breakpoints() {
		$points = array();

		foreach (self::$responsive_breakpoints as $width => $name) {
			$points[$width] = $name . " ($width)";
		}

		return $points;
	}

	/**
	 * returns the breakpoint name by size
	 *
	 * @param string|int size
	 * @return string
	 */
	public static function get_breakpoint_name($size) {
		$size = (string) $size;
		return isset(self::$responsive_breakpoints[$size]) ? self::$responsive_breakpoints[$size] : '';
	}

	/**
	 * @static
	 * @return string
	 */
	public static function get_wrapper_tag() {
		return isset(self::$elements['wrapper']) ? self::$elements['wrapper'] : 'div';
	}

	/**
	 * @static
	 * @return string
	 */
	public static function get_image_tag() {
		return isset(self::$elements['image']) ? self::$elements['image'] : 'div';
	}


	// ! Class Members

	protected $imageSizesCache = null;

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$field = new UploadField('Images');
		$fields->removeFieldFromTab('Root', 'Images');
		$fields->addFieldToTab('Root.Main', $field);
		//$fields 
		return $fields;
	}

	function getThumbnail() {
		$image = $this->Images()->First();
		//$image = $this->getDefaultImage();

		if ($image) {
			return $image->CMSThumbnail();
		}
	}

	public function getImage() {
		return $this->getTag();
	}

	/**
	 * returns a associative array with (size: 320) => (tag: '<img src="../foo.jpg">')
	 *
	 * @return string
	 */
	public function getTagsBySize() {
		$tags = array();

		foreach ($this->getImagesBySize() as $image) {
			$imgs = $image->getResponsiveTagsByWidth();
			$tags = array_unique($tags + $imgs);
		}
		
		return $tags;
	}

	/**
	 * returns the image tag by size
	 *
	 * @param string|int size
	 * @return string
	 */
	public function getTagBySize($size) {
		$images = $this->getTagsBySize();
		$size = (string) $size;

		if (isset($images[$size]) && $images[$size]) {
			return $images[$size];
		}
		else {
			$closestImage = $this->getClosestImage($size);
			if ($closestImage) {
				return $closestImage->getResponsiveTag($size);
			}
			else {
				user_error("couldn't find an image for minimum width {$size}px", E_USER_ERROR);
			}
			//$availableImages = $this->getImagesBySize();
		}
	}

	/**
	 * returns ResponsiveImageObjects by size
	 *
	 * @return array ('size' => ResponsiveImageObject)
	 */
	function getImagesBySize() {
		if (!$this->imageSizesCache) {
			$images = array();

			foreach ($this->Images() as $image) {
				$imgSizes = $image->getMinWidths();
				foreach ($imgSizes as $size) {
					$images[(string) $size] = $image;
				}
			}

			$this->imageSizesCache = $images;
		}
		
		return $this->imageSizesCache;
	}

	public function forTemplate() {
		return $this->getTag();
	}

	/**
	 * returns the image tag for all available image sizes
	 *
	 * @return string
	 */
	public function getTag() {
		$els = $this->stat('elements');
		$wrapperTag = self::get_wrapper_tag();

		isset($els['wrapper']) ? $els['wrapper'] : 'div';
		$imgTag = self::get_image_tag();
		$alt = $this->Title;
		$tag = "<$wrapperTag data-picture data-alt=\"{$this->Title}\">\n";

		// collect images
		$images = $this->getTagsBySize();

		// sort
		$sizes = array_keys(self::$responsive_breakpoints);
		foreach ($sizes as $i => $size) {
			if (isset($images[$size]) && $images[$size]) {
				$tag .= "\t".$images[$size];
			}
			else {
				$closestImage = $this->getClosestImage($size);

				if ($closestImage) {
					$tag .= "\t".$closestImage->getResponsiveTag($size);
				}
				else {
					user_error("couldn't find an image for minimum width {$size}px", E_USER_ERROR);
				}
				//$availableImages = $this->getImagesBySize();
			}
		}

		// ie desktop
		if (self::$support_ie_desktop) {
			$min = (string) self::$ie_desktop_min_width;
			$closestIndex = array_search($min, $sizes);
			// @todo possible insecure: add index check -1
			$ieDesktopImage = $this->getClosestImage($sizes[$closestIndex]);
			$tag .= "\n\t".'<!--[if (lt IE 9) & (!IEMobile)]>'."\n";
			$tag .= "\t\t".$ieDesktopImage->getResponsiveTag($sizes[$closestIndex]);
			$tag .= "\t".'<![endif]-->'."\n";
		}

		// add noscript
		if (self::$add_noscript) {
			$noscriptImage = $this->getClosestImage($sizes[0]);
			$link = $noscriptImage->getResponsiveLink($sizes[0]);
			$alt = Convert::raw2att($this->Title);

			$tag .= "\n\t".'<noscript>'."\n";
			$tag .= "\t\t<img src=\"{$link}\" alt=\"{$alt}\">\n";
			$tag .= "\t".'</noscript>'."\n";
		}

		$tag .= "</$wrapperTag>\n";

		return $tag;
	}

	/**
	 * Searches the sizes up for more images and returns the closest.
	 *
	 * @param int|string size
	 * @return ResponsiveImage|false
	 */
	function getClosestImage($size) {
		if ($this->Images()->Count() == 1) return $this->Images()->First();

		$images = $this->getImagesBySize();
		$sizes = array_keys(self::$responsive_breakpoints);
		$startPos = array_search((string)$size, $sizes);

		for ($i = $startPos; $i < $images; $i++) {
			$size = (string) $sizes[$i];
			if (isset($images[$size])) {
				return $images[$size];
			}
		}

		return false;
	}
}