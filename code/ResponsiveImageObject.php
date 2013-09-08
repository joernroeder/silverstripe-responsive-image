<?php
/**
 *
 * @package ResponsiveImage
 */
class ResponsiveImageObject extends Image {

	private static $db = array(
		'IsRetina'	=> 'Boolean',
		'MinWidth'	=> 'Varchar(50)'
	);

	private static $has_one = array(
		'Responsive' => 'ResponsiveImage'
	);

	private static $default_width_name = 'Small';

	protected $imageTag = null;

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeFieldsFromTab('Root.Main', array(
			'Title',
			'OwnerID',
			'ParentID',
			'Name'
		));

		$breakpoints = ResponsiveImage::get_responsive_breakpoint_sizes();
		$minWidthField = new CheckboxSetField('MinWidth', 'Minimum width of the screen', $breakpoints);


		$fields->addFieldsToTab('Root.Main', array(
			$minWidthField,
			//new CheckboxField('IsRetina', 'Is Retina Version')
		));

		return $fields;
	}

	/**
	 * returns min-widths as array
	 *
	 * @return array
	 */
	function getMinWidths() {
		$minWidths = $this->MinWidth ? $this->MinWidth : (String) $this->getLargestBreakpoint();
		$widths = explode(',', $minWidths);
		sort($widths);

		return $widths;
	}

	function getLargestBreakpoint() {
		$points = array_keys(ResponsiveImage::get_responsive_breakpoints());
		sort($points);

		return end($points);
	}

	function getWidthBySet($set, $width) {
		if ($config = ResponsiveImage::get_set_config($set, $width)) {
			$width = isset($config['size']) ? $config['size'] : $width;
		}

		return $width;
	}

	function getMethodBySet($set, $width) {
		$method = null;
		
		if ($config = ResponsiveImage::get_set_config($set, $width)) {
			$method = isset($config['method']) ? $config['method'] : Config::inst()->get('ResponsiveImage', 'default_method');
		}

		return $method ? $method : 'SetWidth';
	}

	function getImageByWidth($width, $set = null) {
		$size = $width;
		$method = $this->getMethodBySet($set, $width);

		if ($set) {
			$size = $this->getWidthBySet($set, $width);
		}

		return $this->$method($size);
	}

	function setImageTag($value) {
		$this->imageTag = $value;
	}

	/**
	 * returns the image tag for a specific or all sizes
	 *
	 * @todo we should order image tags by min-width 
	 *
	 * @param int size
	 * @param boolean include media infos
	 * @return string
	 */
	function getResponsiveTag($size = null, $includeMedia = true, $set = null) {
		$rSizes = array_keys(ResponsiveImage::get_responsive_breakpoints());
		$tags = '';
		$sizes = $size ? array((string) $size) : $this->getMinWidths();
		$imgTag = $this->imageTag ? $this->imageTag : ResponsiveImage::get_image_tag();
		$retina = $this->IsRetina ? '(min-device-pixel-ratio: 2.0)' : '';

		foreach ($sizes as $s) {
			$mediaAttr = '';

			// include media query and retina info
			if ($includeMedia) {
				// exclude min-width for the smallest size
				$width = $s && $rSizes[0] != $s ? "(min-width: {$s}px)" : '';

				// build data-media
				$and = $width && $retina ? ' and ' : '';
				$media = $width . $and . $retina;
				$mediaAttr = $media ? " data-media=\"$media\"" : '';
			}
			
			// don't scale up
			if ($this->getWidth() > $this->getWidthBySet($set, $s)) {
				$resized = $this->getImageByWidth($s, $set);
				$link = $resized->Link();
				$height = $resized->Height;
				$width = $resized->Width;
			} 
			
			// let the browser scale
			else {
				$link = $this->Link();
				$height = $this->Height;
				$width = $this->Width;
			}

			$ratio = ($height && $width) ? $width / $height : '';

			// return tag
			$tags .= "<$imgTag data-ratio=\"{$ratio}\" data-src=\"{$link}\"$mediaAttr></$imgTag>\n";
		}
			
		return $tags;
	}

	function getLinksBySize($set = null) {
		$sizes = array_keys(ResponsiveImage::get_responsive_breakpoints()); //$this->getMinWidths();
		$urls = array();

		foreach ($sizes as $s) {
			$str_size = (string) $s;

			if ($this->getWidth() > $s) {
				$resized = $this->getImageByWidth($s, $set);
				$urls[$str_size] = $resized->Link();
			}
			else {
				$urls[$str_size] = $this->Link();
			}
		}

		return $urls;
	}

	function getImageDataBySize($set = null) {
		$sizes = array_keys(ResponsiveImage::get_responsive_breakpoints());
		$data = array();

		foreach ($sizes as $size) {
			$str_size = (string) $size;

			$data[$str_size] = array(
				'Width' => $size
			);

			// @todo check against set size
			if ($set) {
				$width = $this->getWidthBySet($set, $size);
			}

			if ($this->getWidth() > $width) {
				$resized = $this->getImageByWidth($size, $set);
				$data[$str_size]['Height'] = $resized->getHeight();
				$data[$str_size]['Url'] = $resized->Link();
			}
			else {
				$data[$str_size]['Height'] = $this->getHeight();
				$data[$str_size]['Url'] = $this->Link();
			}
			
		}
		return $data;
	}

	/**
	 * returns the link for the formatted image
	 *
	 * @param string|int size
	 * @return string
	 */
	function getResponsiveLink($size, $set = null) {
		// don't scale up
		if ($this->getWidth() > $this->getWidthBySet($set, $size)) {
			$resized = $this->getImageByWidth($size, $set);
			$link = $resized->Link();
		} 
		
		// let the browser scale
		else {
			$link = $this->Link();
		}

		return $link;
	}

	function getResponsiveTagsByWidth($set = null) {
		$imgSizes = $this->getMinWidths();
		$sizes = array();

		foreach ($imgSizes as $size) {
			$sizes[(string)$size] = $this->getResponsiveTag($size, true, $set);
		}

		return $sizes;
	}

	function getMinWidthName() {
		$points = ResponsiveImage::get_responsive_breakpoints();

		$names = array();//self::$default_width_name;
		$sizes = explode(',', $this->MinWidth);

		foreach ($sizes as $size) {
			if (isset($points[$size])) {
				$names[] = $points[$size];
			}
		}

		return implode(', ', $names);
	}

	function getTitle() {
		$title = parent::getTitle();
		return "({$this->getMinWidthName()}) - " . $title;
	}

	/*function getTitle() {
		//$minWidth = $this->MinWidth ? '-' . $this->MinWidth : '-default';
		//print_r($this->Responsive());
		return $this->Responsive()->AltText . '-' . $this->getMinWidthName();
	}*/

	/*
	 * sets the min-widths of this image by looking in the filename.
	 * seperate sizes with a dash.
	 * For example "Image01-medium-large.jpg" will be used for "medium" and "large"
	 */
	function onBeforeWrite() {
		parent::onBeforeWrite();

		if ($this->ID) return;

		$sizes = array();
		$name = $this->getField('Name');
		$ext = $this->getExtension();

		// remove extension
		$name = str_replace('.' . $ext, '', $name);
		// remove numbers
		$name = preg_replace('/\d+/i', '', $name);
		
		$namePoints = explode('-', $name);

		$points = ResponsiveImage::get_responsive_breakpoints();
		$pointSizes = array_keys($points);
		$pointNames = array_values($points);

		foreach ($namePoints as $point) {
			$pos = array_search($point, $pointNames);

			if ($pos !== false) {
				$sizes[] = $pointSizes[$pos];
			}
		}

		if (!empty($sizes)) {
			$this->MinWidth = implode(',', $sizes);
		}
	}
	
}