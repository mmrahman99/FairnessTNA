<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */

/**
 * Simple and cross-library package to doing image transformations and
 * manipulations.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Image
 * @package    Image_Transform
 * @author     Vincent Oostindie <vincent@sunlight.tmfweb.nl>
 * @author     Alan Knowles <alan@akbkhome.com>
 * @author     Peter Bowyer <peter@mapledesign.co.uk>
 * @author     Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @copyright  2002-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id: Transform.php,v 1.42 2007/04/19 16:17:57 dufuz Exp $
 * @link       http://pear.php.net/package/Image_Transform
 */

/**
 * Include for error handling
 */
require_once 'PEAR.php';

/**
 * Error code for unsupported library, image format or methods
 */
define('IMAGE_TRANSFORM_ERROR_UNSUPPORTED', 1);

/**
 * Error code for failed transformation operations
 */
define('IMAGE_TRANSFORM_ERROR_FAILED', 2);

/**
 * Error code for failed i/o (Input/Output) operations
 */
define('IMAGE_TRANSFORM_ERROR_IO', 3);

/**
 * Error code for invalid arguments
 */
define('IMAGE_TRANSFORM_ERROR_ARGUMENT', 4);

/**
 * Error code for out-of-bound related errors
 */
define('IMAGE_TRANSFORM_ERROR_OUTOFBOUND', 5);


/**
 * Base class with factory method for backend driver
 *
 * The main "Image_Transform" class is a container and base class which
 * provides a static method for creating an Image object as well as
 * some utility functions (maths) common to all parts of Image_Transform.
 *
 * @category   Image
 * @package    Image_Transform
 * @author     Alan Knowles <alan@akbkhome.com>
 * @author     Peter Bowyer <peter@mapledesign.co.uk>
 * @author     Philippe Jausions <Philippe.Jausions@11abacus.com>
 * @copyright  2002-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Image_Transform
 * @since      PHP 4.0
 */
class Image_Transform
{
    /**
     * Name of the image file
     * @var string
     */
    public static $image = '';

    /**
     * Type of the image file (eg. jpg, gif png ...)
     * @var string
     */
    public static $type = '';

    /**
     * Original image width in x direction
     * @var int
     */
    public static $img_x = '';

    /**
     * Original image width in y direction
     * @var int
     */
    public static $img_y = '';

    /**
     * New image width in x direction
     * @var int
     */
    public static $new_x = '';

    /**
     * New image width in y direction
     * @var int
     */
    public static $new_y = '';

    /**
     * Path to the library used
     * e.g. /usr/local/ImageMagick/bin/ or
     * /usr/local/netpbm/
     */
    public static $lib_path = '';

    /**
     * Flag to warn if image has been resized more than once before displaying
     * or saving.
     */
    public static $resized = false;

    /**
     * @var array General options
     * @access protected
     */
    public static $_options = array(
        'quality' => 75,
        'scaleMethod' => 'smooth',
        'canvasColor' => array(255, 255, 255),
        'pencilColor' => array(0, 0, 0),
        'textColor' => array(0, 0, 0)
    );

    /**
     * Flag for whether settings should be discarded on saving/display of image
     * @var bool
     * @see Image_Transform::keepSettingsOnSave
     */
    public static $keep_settings_on_save = false;

    /**
     * Supported image types
     * @var array
     * @access protected
     */
    public static $_supported_image_types = array();

    /**
     * Initialization error tracking
     * @var object
     * @access private
     **/
    public static $_error = null;

    /**
     * associative array that tracks existence of programs
     * (for drivers using shell interface and a tiny performance
     * improvement if the clearstatcache() is used)
     * @var array
     * @access protected
     */
    public static $_programs = array();

    /**
     * Default parameters used in the addText methods.
     */
    public static $default_text_params = array('text' => 'Default text',
        'x' => 10,
        'y' => 20,
        'color' => 'red',
        'font' => 'Arial.ttf',
        'size' => '12',
        'angle' => 0,
        'resize_first' => false);

    /**
     * Creates a new Image_Transform object
     *
     * @param string $driver name of driver class to initialize. If no driver
     *               is specified the factory will attempt to use 'Imagick' first
     *               then 'GD' second, then 'Imlib' last
     *
     * @return object an Image_Transform object, or PEAR_Error on error
     *
     * @see PEAR::isError()
     * @see Image_Transform::setOption()
     */
    public static function &factory($driver = '')
    {
        if ($driver == '') {
            $extensions = array(
                'imagick' => 'Imagick2',
                'gd' => 'GD',
                'imlib' => 'Imlib');

            foreach ($extensions as $ext => $ext_driver) {
                if (PEAR::loadExtension($ext)) {
                    $driver = $ext_driver;
                    break;
                }
            }
            if (!$driver) {
                return PEAR::raiseError('No image library specified and none can be found.  You must specify driver in factory() call.',
                    IMAGE_TRANSFORM_ERROR_ARGUMENT);
            }
        }
        @include_once 'Image/Transform/Driver/' . basename($driver) . '.php';

        $classname = "Image_Transform_Driver_{$driver}";
        if (!class_exists($classname)) {
            return PEAR::raiseError('Image library not supported... aborting.',
                IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
        }
        $obj = new $classname;

        // Check startup error
        if ($error = $obj->isError()) {
            $obj = $error;
        }
        return $obj;
    }

    /**
     * Returns/sets an error when the instance couldn't initialize properly
     *
     * @param  object PEAR_Error object when setting an error
     * @return mixed FALSE or PEAR_Error object
     * @access protected
     */
    public static function &isError($error = null)
    {
        if (!is_null($error)) {
            self::$_error = $error;
        }
        return self::$_error;
    }

    /**
     * Alias for resize()
     *
     * @see resize()
     */
    public static function scaleByXY($new_x = 0, $new_y = 0, $options = null)
    {
        return self::resize($new_x, $new_y, $options);
    } // End resize

/**
     * Resizes the image in the X and/or Y direction(s)
     *
     * If either is 0 it will keep the original size for that dimension
     *
     * @param mixed $new_x (0, number, percentage 10% or 0.1)
     * @param mixed $new_y (0, number, percentage 10% or 0.1)
     * @param array $options Options
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     */
    public static function resize($new_x = 0, $new_y = 0, $options = null)
    {
        // 0 means keep original size
        $new_x = (0 == $new_x)
            ? self::$img_x
            : self::_parse_size($new_x, self::$img_x);
        $new_y = (0 == $new_y)
            ? self::$img_y
            : self::_parse_size($new_y, self::$img_y);

        // Now do the library specific resizing.
        return self::_resize($new_x, $new_y, $options);
    } // End scaleByX

        /**
     * Parses input for number format and convert
     *
     * If either parameter is 0 it will be scaled proportionally
     *
     * @param mixed $new_size (0, number, percentage 10% or 0.1)
     * @param int $old_size
     * @return mixed Integer or PEAR_error
     * @access protected
     */
    public static function _parse_size($new_size, $old_size)
    {
        if (substr($new_size, -1) == '%') {
            $new_size = substr($new_size, 0, -1);
            $new_size = $new_size / 100;
        }
        if ($new_size > 1) {
            return (int)$new_size;
        } elseif ($new_size == 0) {
            return (int)$old_size;
        } else {
            return (int)round($new_size * $old_size, 0);
        }
    } // End scaleByXY

        /**
     * Place holder for the real resize method
     * used by extended methods to do the resizing
     *
     * @return PEAR_error
     * @access protected
     */
    public static function _resize()
    {
        return PEAR::raiseError('Resize method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    } // End scaleByY

    /**
     * Scales an image by a percentage, factor or a given length
     *
     * This method preserves the aspect ratio
     *
     * @param mixed (number, percentage 10% or 0.1)
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     * @see scaleByPercentage, scaleByFactor, scaleByLength
     */
    public static function scale($size)
    {
        if ((strlen($size) > 1) && (substr($size, -1) == '%')) {
            return self::scaleByPercentage(substr($size, 0, -1));
        } elseif ($size < 1) {
            return self::scaleByFactor($size);
        } else {
            return self::scaleByLength($size);
        }
    } // End scale

    /**
     * Scales an image to a percentage of its original size.  For example, if
     * my image was 640x480 and I called scaleByPercentage(10) then the image
     * would be resized to 64x48
     *
     * @param  int $size Percentage of original size to scale to
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     */
    public static function scaleByPercentage($size)
    {
        return self::scaleByFactor($size / 100);
    } // End scaleByPercentage

    /**
     * Scales an image to a factor of its original size.  For example, if
     * my image was 640x480 and I called scaleByFactor(0.5) then the image
     * would be resized to 320x240.
     *
     * @param float $size Factor of original size to scale to
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     */
    public static function scaleByFactor($size)
    {
        if ($size <= 0) {
            return PEAR::raiseError('New size must be strictly positive',
                IMAGE_TRANSFORM_ERROR_OUTOFBOUND);
        }
        $new_x = round($size * self::$img_x, 0);
        $new_y = round($size * self::$img_y, 0);
        return self::_resize(max(1, $new_x), max(1, $new_y));
    } // End scaleByFactor

        /**
     * Alias for scaleMaxLength
     *
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     * @see scaleMaxLength()
     */
    public static function scaleByLength($size)
    {
        return self::scaleMaxLength($size);
    } // End scaleMaxLength

/**
     * Scales an image so that the longest side has the specified dimension.
     *
     * This method preserves the aspect ratio
     *
     * @param int $size Max dimension in pixels
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     */
    public static function scaleMaxLength($size)
    {
        if ($size <= 0) {
            return PEAR::raiseError('New size must be strictly positive',
                IMAGE_TRANSFORM_ERROR_OUTOFBOUND);
        }
        if (self::$img_x >= self::$img_y) {
            $new_x = $size;
            $new_y = round(($new_x / self::$img_x) * self::$img_y, 0);
        } else {
            $new_y = $size;
            $new_x = round(($new_y / self::$img_y) * self::$img_x, 0);
        }
        return self::_resize(max(1, $new_x), max(1, $new_y));
    }

    /**
     * Fits the image in the specified box size
     *
     * If the image is bigger than the box specified by $width and $height,
     * it will be scaled down to fit inside of it.
     * If the image is smaller, nothing is done.
     *
     * @param  integer $width
     * @param  integer $height
     * @return bool|PEAR_Error TRUE or PEAR_Error object on error
     * @access public
     */
    public static function fit($width, $height)
    {
        if ($width <= 0 || $height <= 0) {
            return PEAR::raiseError("Invalid arguments.",
                IMAGE_TRANSFORM_ERROR_ARGUMENT);
        }
        $x = self::$img_x / $width;
        $y = self::$img_y / $height;
        if ($x <= 1 && $y <= 1) {
            return true;
        } elseif ($x > $y) {
            return self::scaleByX($width);
        } else {
            return self::scaleByY($height);
        }
    }

/**
     * Scales the image to the specified width
     *
     * This method preserves the aspect ratio
     *
     * @param int $new_x Size to scale X-dimension to
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     */
    public static function scaleByX($new_x)
    {
        if ($new_x <= 0) {
            return PEAR::raiseError('New size must be strictly positive',
                IMAGE_TRANSFORM_ERROR_OUTOFBOUND);
        }
        $new_y = round(($new_x / self::$img_x) * self::$img_y, 0);
        return self::_resize(max(1, $new_x), max(1, $new_y));
    }

/**
     * Scales the image to the specified height.
     *
     * This method preserves the aspect ratio
     *
     * @param int $new_y Size to scale Y-dimension to
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     */
    public static function scaleByY($new_y)
    {
        if ($new_y <= 0) {
            return PEAR::raiseError('New size must be strictly positive',
                IMAGE_TRANSFORM_ERROR_OUTOFBOUND);
        }
        $new_x = round(($new_y / self::$img_y) * self::$img_x, 0);
        return self::_resize(max(1, $new_x), max(1, $new_y));
    }

    /**
     * Fits the image in the specified width
     *
     * If the image is wider than the width specified by $width,
     * it will be scaled down to fit inside of it.
     * If the image is smaller, nothing is done.
     *
     * @param integer $width
     * @return bool|PEAR_Error TRUE or PEAR_Error object on error
     * @access public
     */
    public static function fitX($width)
    {
        return (self::$img_x <= $width) ? true : self::scaleByX($width);
    }

    /**
     * Fits the image in the specified height
     *
     * If the image is taller than the height specified by $height,
     * it will be scaled down to fit inside of it.
     * If the image is smaller, nothing is done.
     *
     * @param integer $height
     * @return bool|PEAR_Error TRUE or PEAR_Error object on error
     * @access public
     */
    public static function fitY($height)
    {
        return (self::$img_y <= $height) ? true : self::scaleByY($height);
    }

    /**
     * Sets one options
     *
     * @param  string Name of option
     * @param  mixed  Value of option
     * @access public
     * @see setOptions()
     */
    public static function setOption($name, $value)
    {
        self::$_options[$name] = $value;
    }

    /**
     * Sets multiple options at once
     *
     * Associative array of options:
     *  - quality     (Integer: 0: poor - 100: best)
     *  - scaleMethod ('smooth', 'pixel')
     *
     * @param  array $options Array of options
     * @access public
     */
    public static function setOptions($options)
    {
        self::$_options = array_merge(self::$_options, $options);
    }

    /**
     * Sets the image type (in lowercase letters), the image height and width.
     *
     * @return mixed TRUE or PEAR_error
     * @access protected
     * @see PHP_Compat::image_type_to_mime_type()
     * @link http://php.net/getimagesize
     */
    public static function _get_image_details($image)
    {
        $data = @getimagesize($image);
        //  1 = GIF,   2 = JPG,  3 = PNG,  4 = SWF,  5 = PSD,  6 = BMP,
        //  7 = TIFF (intel byte order),   8 = TIFF (motorola byte order),
        //  9 = JPC,  10 = JP2, 11 = JPX, 12 = JB2, 13 = SWC, 14 = IFF,
        // 15 = WBMP, 16 = XBM
        if (!is_array($data)) {
            return PEAR::raiseError("Cannot fetch image or images details.", true);
        }

        switch ($data[2]) {
            case IMAGETYPE_GIF:
                $type = 'gif';
                break;
            case IMAGETYPE_JPEG:
                $type = 'jpeg';
                break;
            case IMAGETYPE_PNG:
                $type = 'png';
                break;
            case IMAGETYPE_SWF:
                $type = 'swf';
                break;
            case IMAGETYPE_PSD:
                $type = 'psd';
                break;
            case IMAGETYPE_BMP:
                $type = 'bmp';
                break;
            case IMAGETYPE_TIFF_II:
            case IMAGETYPE_TIFF_MM:
                $type = 'tiff';
                break;
            case IMAGETYPE_JPC:
                $type = 'jpc';
                break;
            case IMAGETYPE_JP2:
                $type = 'jp2';
                break;
            case IMAGETYPE_JPX:
                $type = 'jpx';
                break;
            case IMAGETYPE_JB2:
                $type = 'jb2';
                break;
            case IMAGETYPE_SWC:
                $type = 'swc';
                break;
            case IMAGETYPE_IFF:
                $type = 'iff';
                break;
            case IMAGETYPE_WBMP:
                $type = 'wbmp';
                break;
            case IMAGETYPE_XBM:
                $type = 'xbm';
                break;
            default:
                return PEAR::raiseError("Cannot recognize image format",
                    IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
        }
        self::$img_x = self::$new_x = $data[0];
        self::$img_y = self::$new_y = $data[1];
        self::$type = $type;

        return true;
    }

    /**
     * Returns an angle between 0 and 360 from any angle value
     *
     * @param  float $angle The angle to normalize
     * @return float the angle
     * @access protected
     */
    public static function _rotation_angle($angle)
    {
        $angle %= 360;
        return ($angle < 0) ? $angle + 360 : $angle;
    }

    /**
     * Returns the current value of $this->default_text_params.
     *
     * @return array $this->default_text_params The current text parameters
     * @access protected
     */
    public static function _get_default_text_params()
    {
        return self::$default_text_params;
    }

    /**
     * Sets the image width
     *
     * @param int $size dimension to set
     * @access protected
     * @since 29/05/02 13:36:31
     */
    public static function _set_img_x($size)
    {
        self::$img_x = $size;
    }

    /**
     * Sets the image height
     *
     * @param int $size dimension to set
     * @access protected
     * @since 29/05/02 13:36:31
     */
    public static function _set_img_y($size)
    {
        self::$img_y = $size;
    }

    /**
     * Sets the new image width
     *
     * @param int $size dimension to set
     * @access protected
     * @since 29/05/02 13:36:31
     */
    public static function _set_new_x($size)
    {
        self::$new_x = $size;
    }

    /**
     * Sets the new image height
     *
     * @param int $size dimension to set
     * @since 29/05/02 13:36:31
     * @access protected
     */
    public static function _set_new_y($size)
    {
        self::$new_y = $size;
    }

    /**
     * Returns the type of the image being manipulated
     *
     * @return string the image type
     * @access public
     */
    public static function getImageType()
    {
        return self::$type;
    }

    /**
     * Returns the image width
     *
     * @return int the width of the image
     * @access public
     */
    public static function getImageWidth()
    {
        return self::$img_x;
    }

    /**
     * Returns the image height
     *
     * @return int the width of the image
     * @access public
     */
    public static function getImageHeight()
    {
        return self::$img_y;
    }

    /**
     * Returns the image size and extra format information
     *
     * @return array The width and height of the image
     * @access public
     * @see PHP::getimagesize()
     */
    public static function getImageSize()
    {
        return array(
            self::$img_x,
            self::$img_y,
            self::_convert_image_type(self::$type),
            'height="' . self::$img_y . '" width="' . self::$img_x . '"',
            'mime' => self::getMimeType());
    }

    /**
     * Returns the matching IMAGETYPE_* constant for a given image type
     *
     * @param  mixed $type String (GIF, JPG,...)
     * @return mixed string or integer or input on error
     * @access protected
     * @see PHP_Compat::image_type_to_mime_type()
     **/
    public static function _convert_image_type($type)
    {
        switch (strtolower($type)) {
            case 'gif':
                return IMAGETYPE_GIF;
            case 'jpeg':
            case 'jpg':
                return IMAGETYPE_JPEG;
            case 'png':
                return IMAGETYPE_PNG;
            case 'swf':
                return IMAGETYPE_SWF;
            case 'psd':
                return IMAGETYPE_PSD;
            case 'bmp':
                return IMAGETYPE_BMP;
            case 'tiff':
                return IMAGETYPE_TIFF_II;
            //IMAGETYPE_TIFF_MM;
            case 'jpc':
                return IMAGETYPE_JPC;
            case 'jp2':
                return IMAGETYPE_JP2;
            case 'jpx':
                return IMAGETYPE_JPX;
            case 'jb2':
                return IMAGETYPE_JB2;
            case 'swc':
                return IMAGETYPE_SWC;
            case 'iff':
                return IMAGETYPE_IFF;
            case 'wbmp':
                return IMAGETYPE_WBMP;
            case 'xbm':
                return IMAGETYPE_XBM;
            default:
                return $type;
        }

        return (isset($types[$t = strtolower($type)])) ? $types[$t] : $type;
    }

    /**
     * Returns the MIME type of the image being manipulated
     *
     * @param  string $type Image type to get MIME type for
     * @return string The MIME type if available, or an empty string
     * @access public
     * @see PHP_Compat::image_type_to_mime_type()
     * @link http://php.net/image_type_to_mime_type
     */
    public static function getMimeType($type = null)
    {
        return image_type_to_mime_type(self::_convert_image_type(($type) ? $type : self::$type));
    }

    /**
     * This looks at the current image type and attempts to determine which
     * web-safe format will be most suited.  It does not work brilliantly with
     * *.png images, because it is very difficult to know whether they are
     * 8-bit or greater.  Guess I need to have fatter code here :-)
     *
     * @return string web-safe image type
     * @access public
     */
    public static function getWebSafeFormat()
    {
        switch (self::$type) {
            case 'gif':
            case 'png':
                return 'png';
                break;
            default:
                return 'jpeg';
        } // switch
    }

    /**
     * Handles space in path and Windows/UNIX difference
     *
     * @param  string $path Base dir
     * @param  string $command Command to execute
     * @param  string $args Arguments to pass to the command
     * @return string A prepared string suitable for exec()
     * @access protected
     */
    public static function _prepare_cmd($path, $command, $args = '')
    {
        if (!OS_WINDOWS
            || !preg_match('/\s/', $path)
        ) {
            return $path . $command . ' ' . $args;
        }
        return 'start /D "' . $path . '" /B ' . $command . ' ' . $args;
    }

    /**
     * Loads an image file to work with
     *
     * Place holder for the real load method
     * used by extended methods to do the resizing
     *
     * @return PEAR_error
     * @access public
     */
    public static function load($filename)
    {
        return PEAR::raiseError('load() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Outputs the image to standard output
     *
     * Place holder for the real display method
     * used by extended methods to do the resizing
     *
     * @param string $type Format of image to save as
     * @param mixed $quality Format-dependent
     * @return PEAR_error
     * @access public
     */
    public static function display($type, $quality = null)
    {
        return PEAR::raiseError('display() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Returns if the driver supports a given image type
     *
     * @param  string $type Image type (GIF, PNG, JPEG...)
     * @param  string $mode 'r' for read, 'w' for write, 'rw' for both
     * @return TRUE if type (and mode) is supported FALSE otherwise
     * @access public
     */
    public static function supportsType($type, $mode = 'rw')
    {
        return (strpos(@self::$_supported_image_types[strtolower($type)], $mode) === false) ? false : true;
    }

    /**
     * Saves image to file
     *
     * Place holder for the real save method
     * used by extended methods to do the resizing
     *
     * @param string $filename Filename to save image to
     * @param string $type Format of image to save as
     * @param mixed $quality Format-dependent
     * @return PEAR_error
     * @access public
     */
    public static function save($filename, $type, $quality = null)
    {
        return PEAR::raiseError('save() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Releases resource
     *
     * Place holder for the real free method
     * used by extended methods to do the resizing
     *
     * @return PEAR_error
     * @access public
     */
    public static function free()
    {
        return PEAR::raiseError('free() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    public static function _send_display_headers($type)
    {
        // Find the filename of the original image:
        $filename = explode('.', basename(self::$image));
        $filename = $filename[0];
        header('Content-type: ' . self::getMimeType($type));
        header('Content-Disposition: inline; filename=' . $filename . '.' . $type);
    }

    /**
     * Converts an array of RGB value into a #FFFFFF format color.
     *
     * @param  array $color 3-element array with 0-255 values
     * @return mixed A color following the #FFFFFF format or FALSE
     *               if the array couldn't be converted
     * @access public
     *
     * @see rgb2colorname
     * @see colorhex2colorarray
     */
    public static function colorarray2colorhex($color)
    {
        if (!is_array($color)) {
            return false;
        }
        $color = sprintf('#%02X%02X%02X', @$color[0], @$color[1], @$color[2]);
        return (strlen($color) != 7) ? false : $color;
    }

    /**
     * Returns the temp directory according to either the TMP, TMPDIR, or TEMP env
     * variables. If these are not set it will also check for the existence of
     * /tmp, %WINDIR%\temp
     *
     * @access public
     * @return string The system tmp directory
     */
    public static function getTempDir()
    {
        include_once 'System.php';
        return System::tmpdir();
    }

    /*** These snitched from the File package.  Saves including another class! ***/

    /**
     * Returns a temporary filename using tempnam() and the above getTmpDir() function.
     *
     * @access public
     * @param  string $dirname Optional directory name for the tmp file
     * @return string          Filename and path of the tmp file
     */
    public static function getTempFile($dirname = null)
    {
        return tempnam((is_null($dirname)) ? System::tmpdir() : $dirname, 'temp.');
    }

    public static function keepSettingsOnSave($bool)
    {
        self::$keep_settings_on_save = $bool;
    }

    public static function addText()
    {
        return PEAR::raiseError('addText() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /* Methods to add to the driver classes in the future */

    public static function addDropShadow()
    {
        return PEAR::raiseError('addDropShadow() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    public static function addBorder()
    {
        return PEAR::raiseError('addBorder() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Crops an image
     *
     * @param int width Cropped image width
     * @param int height Cropped image height
     * @param int x X-coordinate to crop at
     * @param int y Y-coordinate to crop at
     *
     * @return mixed TRUE or a PEAR_Error object on error
     * @access public
     **/
    public static function crop($width, $height, $x = 0, $y = 0)
    {
        return PEAR::raiseError('crop() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    public static function canvasResize()
    {
        return PEAR::raiseError('canvasResize() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Corrects the gamma of an image
     *
     * @param float $outputgamma Gamma correction factor
     * @return mixed TRUE or a PEAR_error object on error
     * @access public
     **/
    public static function gamma($outputgamma = 1.0)
    {
        return PEAR::raiseError('gamma() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Rotates the image clockwise
     *
     * @param float $angle angle of rotation in degres
     * @param mixed $options
     * @return bool|PEAR_Error TRUE on success, PEAR_Error object on error
     * @access public
     */
    public static function rotate($angle, $options = null)
    {
        return PEAR::raiseError('rotate() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Horizontal mirroring
     *
     * @return mixed TRUE or PEAR_Error object on error
     * @access public
     * @see flip()
     **/
    public static function mirror()
    {
        return PEAR::raiseError('mirror() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Vertical mirroring
     *
     * @return TRUE or PEAR Error object on error
     * @access public
     * @see mirror()
     **/
    public static function flip()
    {
        return PEAR::raiseError('flip() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * @see greyscale()
     **/
    public static function grayscale()
    {
        return self::greyscale();
    }

    /**
     * Converts an image into greyscale colors
     *
     * @return mixed TRUE or a PEAR error object on error
     * @access public
     **/
    public static function greyscale()
    {
        return PEAR::raiseError('greyscale() method not supported by driver',
            IMAGE_TRANSFORM_ERROR_UNSUPPORTED);
    }

    /**
     * Returns a color option
     *
     * @param string $colorOf one of 'canvasColor', 'pencilColor', 'fontColor'
     * @param array $options
     * @param array $default default value to return if color not found
     * @return array an RGB color array
     * @access protected
     */
    public static function _getColor($colorOf, $options = array(), $default = array(0, 0, 0))
    {
        $opt = array_merge(self::$_options, (array)$options);
        if (isset($opt[$colorOf])) {
            $color = $opt[$colorOf];
            if (is_array($color)) {
                return $color;
            }
            if ($color{0} == '#') {
                return self::colorhex2colorarray($color);
            }
            static $colornames = array();
            include_once 'Image/Transform/Driver/ColorsDefs.php';
            return (isset($colornames[$color])) ? $colornames[$color] : $default;
        }
        return $default;
    }

    /**
     * Converts a color string into an array of RGB values
     *
     * @param  string $colorhex A color following the #FFFFFF format
     * @return array 3-element array with 0-255 values
     * @access public
     *
     * @see rgb2colorname
     * @see colorarray2colorhex
     */
    public static function colorhex2colorarray($colorhex)
    {
        $r = hexdec(substr($colorhex, 1, 2));
        $g = hexdec(substr($colorhex, 3, 2));
        $b = hexdec(substr($colorhex, 5, 2));
        return array($r, $g, $b, 'type' => 'RGB');
    }

    /**
     * Returns an option
     *
     * @param string $name name of option
     * @param array $options local override option array
     * @param mixed $default default value to return if option is not found
     * @return mixed the option
     * @access protected
     */
    public static function _getOption($name, $options = array(), $default = null)
    {
        $opt = array_merge(self::$_options, (array)$options);
        return (isset($opt[$name])) ? $opt[$name] : $default;
    }

    /**
     * Checks if the rectangle passed intersects with the current image
     *
     * @param int $width
     * @param int $height
     * @param int $x X-coordinate
     * @param int $y Y-coordinate
     * @return bool|PEAR_Error TRUE if intersects, FALSE if not, and PEAR_Error on error
     * @access public
     */
    public static function intersects($width, $height, $x, $y)
    {
        $left = $x;
        $right = $x + $width;
        if ($right < $left) {
            $left = $right;
            $right = $x;
        }
        $top = $y;
        $bottom = $y + $height;
        if ($bottom < $top) {
            $top = $bottom;
            $bottom = $y;
        }
        return (bool)($left < self::$new_x
            && $right >= 0
            && $top < self::$new_y
            && $bottom >= 0);
    }
}
