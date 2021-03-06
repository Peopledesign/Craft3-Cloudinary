<?php
namespace futureactivities\cloudinary\services;

use craft\base\Component;

class Transform extends Component
{
    /**
     * Transform an image
     * 
     * @param Asset $image Craft Asset
     * @param array $sizes A list of sizes for this image
     * @param array $options Additional options
     */
    public function image($image, $sizes = [], $options = [])
    {
        if(!isset($image->filename)){
            return [];
        }
        // Secure sign the cloudinary URL
        $options['sign_url'] = true;
        
        // No alt tag specified?
        if (!isset($options['alt']) && isset($image->title) )
            $options['alt'] = $image->title;
            
        // Scale and crop
        if (isset($options['scaleAndCrop']) && $options['scaleAndCrop']) {
            $sizes = $this->scaleAndCrop($image, $sizes);
            unset($options['scaleAndCrop']);
        }
        
        // if image is within a Cloudinary folder, add the folder path
        if (isset($image->folderPath)) {
            $urls = $this->generateUrls(str_replace(" ","%20",$image->folderPath).$image->filename, $sizes, $options);
        } else {
            $urls = $this->generateUrls($image->filename, $sizes, $options);
        }
        
        return [
            'alt' => $options['alt'],
            'src' => reset($urls),
            'srcset' => $this->generateSrcSet($urls)
        ];
    }
    
    /**
     * Scale and crop an image.
     *
     * By default, Cloudinary can only scale OR crop. In craft we want to scale
     * then crop using the focal point specified.
     */
    protected function scaleAndCrop($image, $sizes)
    {
        $focalPoint = $image->getFocalPoint();
         
        foreach ($sizes AS &$size) {
            
            $quality = 100;
            if (isset($size['quality']))
                $quality = $size['quality'];
                
            $ratio = $image->width / $image->height;
                
            $size['transformation'] = [];
            if ($image->width / $size['width'] < $image->height / $size['height']) {
                $size['transformation'][] = ['width' => $size['width'], 'crop' => 'scale', 'quality' => $quality];
                $width = $size['width'];
                $height = $size['width'] / $ratio;
            } else {
                $size['transformation'][] = ['height' => $size['height'], 'crop' => 'scale', 'quality' => $quality];
                $width = $size['height'] * $ratio;
                $height = $size['height'];
            }
            
            $x = strval(round($focalPoint['x'] * $width));
            $y = strval(round($focalPoint['y'] * $height));
            
            $size['transformation'][] = ['height' => $size['height'], 'width' => $size['width'], 'crop' => 'crop', 'gravity' => 'xy_center', 'x' => $x, 'y' => $y, 'quality' => $quality];
        }
        
        return $sizes;
    }
    
    /**
     * Generate the cloudinary URLs for the list of sizes
     * 
     * @param array $sizes The list of sizes we need to generate
     * @param array $options Additional cloudinary options to apply to all sizes
     */
    protected function generateUrls($filename, $sizes, $options)
    {
        $result = [];
        
        foreach ($sizes AS $size) {
            $sizeOptions = array_merge($size, $options);
            $result[$size['width']] = cloudinary_url($filename, $sizeOptions);
        }
        
        return $result;
    }
    
    /**
     * Generate the srcset string
     * 
     * @param array $urls The list of Cloudinary URLs
     */
    protected function generateSrcSet($urls)
    {
        if (count($urls) < 2) return;
        
        $srcset = array_map(function($value, $key) {
            return $value.' '.$key.'w';
        }, array_values($urls), array_keys($urls));
        
        return implode(',', $srcset);
    }
}
