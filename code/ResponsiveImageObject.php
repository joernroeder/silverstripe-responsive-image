<?php
/**
 *
 * @package ResponsiveImage
 */
class ResponsiveImageObject extends Image {

	static $db = array(
		'IsRetina'	=> 'Boolean',
		'MinWidth'	=> 'Varchar(50)'
	);

	static $has_one = array(
		'Responsive' => 'ResponsiveImage'
	);

	static $default_width_name = 'Small';

	protected $imageTag = null;

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->removeFieldsFromTab('Root.Main', array(
			'Title',
			'OwnerID',
			'ParentID',
			'Name'
		));

		$breakpoints = ResponsiveImage::get_responsive_breakpoints();
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
		$points = array_keys(ResponsiveImage::$responsive_breakpoints);
		sort($points);

		return end($points);
	}

	function getImageByWidth($width) {
		return $this->SetWidth($width);
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
	function getResponsiveTag($size = null, $includeMedia = true) {
		$rSizes = array_keys(ResponsiveImage::$responsive_breakpoints);
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
			if ($this->getWidth() > $s) {
				$resized = $this->getImageByWidth($s);
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

	function getLinksBySize() {
		$sizes = array_keys(ResponsiveImage::$responsive_breakpoints); //$this->getMinWidths();
		$urls = array();

		foreach ($sizes as $s) {
			$str_size = (string) $s;

			if ($this->getWidth() > $s) {
				$resized = $this->getImageByWidth($s);
				$urls[$str_size] = $resized->Link();
			}
			else {
				$urls[$str_size] = $this->Link();
			}
		}

		return $urls;
	}

	function getImageDataBySize() {
		$sizes = array_keys(ResponsiveImage::$responsive_breakpoints);
		$data = array();

		foreach ($sizes as $size) {
			$str_size = (string) $size;

			$data[$str_size] = array(
				'Width' => $size
			);

			if ($this->getWidth() > $size) {
				$resized = $this->getImageByWidth($size);
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
	function getResponsiveLink($size) {
		// don't scale up
		if ($this->getWidth() > $size) {
			$resized = $this->getImageByWidth($size);
			$link = $resized->Link();
		} 
		
		// let the browser scale
		else {
			$link = $this->Link();
		}

		return $link;
	}

	function getResponsiveTagsByWidth() {
		$imgSizes = $this->getMinWidths();
		$sizes = array();

		foreach ($imgSizes as $size) {
			$sizes[(string)$size] = $this->getResponsiveTag($size);
		}

		return $sizes;
	}

	function getMinWidthName() {
		$points = ResponsiveImage::$responsive_breakpoints;

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

		$points = ResponsiveImage::$responsive_breakpoints;
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