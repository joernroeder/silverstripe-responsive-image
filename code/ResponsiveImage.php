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

	/**
	 * [$responsive_breakpoints description]
	 *
	 * @config
	 * @var array
	 */
	private static $responsive_breakpoints = array(
		'320' => 'mini',
		'480' => 'small',
		'768' => 'medium',
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
	private static $support_ie_desktop = true;

	/**
	 * sets the min-width for IE desktops
	 *
	 * @static
	 */
	private static $ie_desktop_min_width = 640;


	/**
	 * Fallback content for non-JS browsers.
	 *
	 * @static
	 */
	private static $add_noscript = true;

	/**
	 * Tag names for wrapper and image element
	 *
	 * @static
	 */
	private static $default_elements = array(
		'wrapper'	=> 'div',
		'image'		=> 'div'
	);

	protected $wrapperElement = null;
	protected $imageElement = null;


	// ! Statics

	private static $db = array(
		'Title' => 'Varchar'
	);

	private static $has_many = array(
		'Images' => 'ResponsiveImageObject'
	);

	private static $summary_fields = array(
		'Thumbnail',
		'Title',
		'ID'
	);

	private static $searchable_fields = array(
		'Title',
	);

	public static function get_responsive_breakpoints() {
		$breakPoints = array();
		$points = self::config()->break_points;

		foreach ($points as $dim => $point) {
			$breakPoints[$dim] = isset($point['name']) ? $point['name'] : (string) $dim;
		}

		return $breakPoints;
		//return self::config()->responsive_breakpoints;
	}

	public static function get_responsive_breakpoint_sizes() {
		$points = array();

		foreach (self::get_responsive_breakpoints() as $width => $name) {
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
		$points = self::get_responsive_breakpoint_sizes();
		$size = (string) $size;
		return isset($points[$size]) ? self::$points[$size] : '';
	}

	/**
	 * @static
	 * @return string
	 */
	public static function get_wrapper_tag() {
		return isset(self::config()->default_elements['wrapper']) ? self::config()->default_elements['wrapper'] : 'div';
	}

	/**
	 * @static
	 * @return string
	 */
	public static function get_image_tag() {
		return isset(self::config()->default_elements['image']) ? self::config()->default_elements['image'] : 'div';
	}

	/**
	 *
	 * @static
	 * @return array
	 */
	public static function get_set_config($set, $width) {
		$breakpoints = self::config()->break_points;

		if (isset($breakpoints[$width]['sets'][$set])) {
			return $breakpoints[$width]['sets'][$set];
		}
		else {
			return false;
		}
	}


	// ! Class Members

	/**
	 * @var $extraClasses array Extra CSS-classes for the formfield-container
	 */
	protected $extraClasses;

	protected $imageSizesCache = null;
	protected $urlSizesCache = null;
	protected $imageDataCache = null;

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$field = new UploadField('Images');
		$fields->removeFieldFromTab('Root', 'Images');
		$fields->addFieldToTab('Root.Main', $field);
		//$fields 
		return $fields;
	}

	public function getThumbnail() {
		$image = $this->Images()->First();

		if ($image) {
			return $image->CMSThumbnail();
		}
	}

	/**
	 * Compiles all CSS-classes.
	 * 
	 * @return string CSS-classnames
	 */
	public function extraClass() {
		$classes = array();

		if($this->extraClasses) $classes = array_merge($classes, array_values($this->extraClasses));

		return implode(' ', $classes);
	}

	/**
	 * Add a CSS-class to the formfield-container.
	 * 
	 * @param $class String
	 */
	public function addExtraClass($class) {
		$this->extraClasses[$class] = $class;
		return $this;
	}

	/**
	 * Remove a CSS-class from the formfield-container.
	 * 
	 * @param $class String
	 */
	public function removeExtraClass($class) {
		$pos = array_search($class, $this->extraClasses);
		if($pos !== false) unset($this->extraClasses[$pos]);

		return $this;
	}

	/**
	 * returns the image tag
	 *
	 * @param string wrapper tagname
	 * @param string image tagname
	 *
	 * @return string
	 */
	public function getImage($wrapper = null, $image = null, $set = null) {
		return $this->getTag($wrapper, $image, $set);
	}

	public function Image($wrapper = null, $image = null, $set = null) {
		return $this->getImage($wrapper, $image, $set);
	}

	/**
	 * returns a associative array with (size: 320) => (tag: '<img src="../foo.jpg">')
	 *
	 * @return string
	 */
	public function getTagsBySize($set = null) {
		$imgTag = $this->imageElement ? $this->imageElement : self::get_image_tag();
		$tags = array();

		foreach ($this->getImagesBySize() as $image) {
			if ($image) {
				$image->setImageTag($imgTag);
				$imgs = $image->getResponsiveTagsByWidth($set);
				$tags = array_unique($tags + $imgs);
			}
		}

		return $tags;
	}

	/**
	 * returns the image tag by size
	 *
	 * @param string|int size
	 * @return string
	 */
	public function getTagBySize($size, $set = null) {
		$images = $this->getTagsBySize();
		$size = (string) $size;

		if (isset($images[$size]) && $images[$size]) {
			return $images[$size];
		}
		else {
			$closestImage = $this->getClosestImage($size);
			if ($closestImage) {
				return $closestImage->getResponsiveTag($size, true, $set);
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

	function getLinksBySize($set = null) {
		if (!$this->urlSizeCache) {
			$urls = array();

			foreach ($this->Images() as $image) {
				$urls[] = $image->getLinksBySize($set);
			}

			$this->urlSizesCache = $urls;
		}

		return $this->urlSizesCache;
	}

	function getImageDataBySize($set = null) {
		if (!$this->imageDataCache) {
			$datas = array();

			foreach ($this->Images() as $image) {
				$datas[] = $image->getImageDataBySize($set);
			}

			$this->imageDataCache = $datas;
		}

		return $this->imageDataCache;
	}

	public function forTemplate() {
		return $this->getTag();
	}

	/**
	 * returns the image tag for all available image sizes
	 *
	 * @return string
	 */
	public function getTag($wrapper = null, $image = null, $set = null) {
		$this->wrapperElement = $wrapper;
		$this->imageElement = $image;

		$wrapperTag = $this->wrapperElement ? $this->wrapperElement : self::get_wrapper_tag();
		$imgTag = $this->imageElement ? $this->imageElement : self::get_image_tag();

		$alt = $this->Title;
		$extraClass = $this->extraClass();
		$extraClass = $extraClass ? " class=\"{$extraClass}\"" : '';
		$tag = "<$wrapperTag data-lazy-picture data-picture data-alt=\"{$this->Title}\"{$extraClass}>\n";

		// collect images
		$images = $this->getTagsBySize();

		// sort
		$sizes = array_keys(self::get_responsive_breakpoints());
		foreach ($sizes as $i => $size) {
			if (isset($images[$size]) && $images[$size]) {
				$tag .= "\t".$images[$size];
			}
			else {
				$closestImage = $this->getClosestImage($size);

				if ($closestImage) {
					$closestImage->setImageTag($imgTag);
					$tag .= "\t" .  $closestImage->getResponsiveTag($size, true, $set);
				}
				else {
					//user_error("couldn't find an image for minimum width {$size}px", E_USER_WARNING);
					return '';
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

			if ($ieDesktopImage) {
				$ieDesktopImage->setImageTag($imgTag);
				$tag .= "\n\t".'<!--[if (lt IE 9) & (!IEMobile)]>'."\n";
				$tag .= "\t\t".$ieDesktopImage->getResponsiveTag($sizes[$closestIndex], true, $set);
				$tag .= "\t".'<![endif]-->'."\n";
			}
		}

		// add noscript
		if (self::$add_noscript) {
			$noscriptImage = $this->getClosestImage($sizes[0]);

			if ($noscriptImage) {
				$link = $noscriptImage->getResponsiveLink($sizes[0], $set);
				$alt = Convert::raw2att($this->Title);

				$tag .= "\n\t".'<noscript>'."\n";
				$tag .= "\t\t<img src=\"{$link}\" alt=\"{$alt}\">\n";
				$tag .= "\t".'</noscript>'."\n";
		}
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
	function getClosestImage($size, $set = null) {
		if ($this->Images()->Count() < 1) return false;
		if ($this->Images()->Count() == 1) return $this->Images()->First();

		$images = $this->getImagesBySize();
		$sizes = array_keys(self::get_responsive_breakpoints());
		$startPos = array_search((string)$size, $sizes);

		if ($images) {
			for ($i = $startPos; $i < sizeof($images); $i++) {
				$size = isset($sizes[$i]) ? (string) $sizes[$i]: false;
				if ($size && isset($images[$size])) {
					return $images[$size];
				}
			}

			$imageKeys = array_keys($images);
			sort($imageKeys);
			
			return $images[end($imageKeys)];
		}

		return false;
	}

	public function getLargestImage() {
		$breakPoints = array_keys(self::get_responsive_breakpoints());
		$largestSize = end($breakPoints);
		
		return $this->getClosestImage($largestSize);
	}

	public function onBeforeDelete() {
 		parent::onBeforeDelete();
 		foreach ($this->Images() as $responsiveImage) {
 			$responsiveImage->delete();
 		}
 	}
}