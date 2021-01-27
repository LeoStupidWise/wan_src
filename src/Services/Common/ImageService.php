<?php

namespace Wan\Services\Common;

class ImageService
{
    public function createWordsWatermark(
        $imgurl, $text, $fontSize='14', $color='0,0,0', $point='1', $font='Arial.ttf', $angle=0, $newimgurl=''
    ) {
        $imageCreateFunArr = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif'
        ];
        $imageOutputFunArr = [
            'image/jpeg' => 'imagejpeg',
            'image/png' => 'imagepng',
            'image/gif' => 'imagegif'
        ];

        //获取图片的mime类型
        $imgsize = getimagesize($imgurl);
        if (empty($imgsize)) {
            return resultError(4001, '不是合法的图片类型');
        }

        $imgWidth = $imgsize[0];
        $imgHeight = $imgsize[1];
        $imgMime = $imgsize['mime'];
        if (!isset($imageCreateFunArr[$imgMime])) {
            return resultError(4002, '不是合法的图片类型');
        }
        if (!isset($imageOutputFunArr[$imgMime])) {
            return resultError(4003, '不是合法的图片类型');
        }

        $imageCreateFun = $imageCreateFunArr[$imgMime];
        $imageOutputFun = $imageOutputFunArr[$imgMime];

        $im = $imageCreateFun($imgurl);

        /*
         * 参数判断
         */
        $color = explode(',', $color);
        $text_color = imagecolorallocate($im, intval($color[0]), intval($color[1]), intval($color[2])); //文字水印颜色
        $point = intval($point) > 0 && intval($point) < 10 ? intval($point) : 1; //文字水印所在的位置
        $fontSize = intval($fontSize) > 0 ? intval($fontSize) : 14;
        $angle = ($angle >= 0 && $angle < 90 || $angle > 270 && $angle < 360) ? $angle : 0; //判断输入的angle值有效性
        $font_dir = YiiBase::getPathofAlias('webroot') .'/public/css/';
        $fontUrl = $font_dir.'vista.ttf'; //有效字体未验证
        $text = explode('|', $text);
        $newimgurl = $newimgurl ? $newimgurl : $imgurl . '_WordsWatermark.jpg'; //新图片地址 统一图片后缀

        /**
         *  根据文字所在图片的位置方向，计算文字的坐标
         * 首先获取文字的宽，高， 写一行文字，超出图片后是不显示的
         */
        $textLength = count($text) - 1;
        $maxtext = 0;
        foreach ($text as $val) {
            $maxtext = strlen($val) > strlen($maxtext) ? $val : $maxtext;
        }
        $textSize = imagettfbbox($fontSize, 0, $fontUrl, $maxtext);
        $textWidth = $textSize[2] - $textSize[1]; //文字的最大宽度
        $textHeight = $textSize[1] - $textSize[7]; //文字的高度
        $lineHeight = $textHeight + 3; //文字的行高
        //是否可以添加文字水印 只有图片的可以容纳文字水印时才添加
        if ($textWidth + 40 > $imgWidth || $lineHeight * $textLength + 40 > $imgHeight) {
            return false; //图片太小了，无法添加文字水印
        }

        if ($point == 1) { //左上角
            $porintLeft = 20;
            $pointTop = 20;
        } elseif ($point == 2) { //上中部
            $porintLeft = floor(($imgWidth - $textWidth) / 2);
            $pointTop = 20;
        } elseif ($point == 3) { //右上部
            $porintLeft = $imgWidth - $textWidth - 20;
            $pointTop = 20;
        } elseif ($point == 4) { //左中部
            $porintLeft = 35;
            $pointTop = floor(($imgHeight - $textLength * $lineHeight) / 2);
        } elseif ($point == 5) { //正中部
            $porintLeft = floor(($imgWidth - $textWidth) / 2);
            $pointTop = floor(($imgHeight - $textLength * $lineHeight) / 2);
        } elseif ($point == 6) { //右中部
            $porintLeft = $imgWidth - $textWidth - 20;
            $pointTop = floor(($imgHeight - $textLength * $lineHeight) / 2);
        } elseif ($point == 7) { //左下部
            $porintLeft = 20;
            $pointTop = $imgHeight - $textLength * $lineHeight - 20;
        } elseif ($point == 8) { //中下部
            $porintLeft = floor(($imgWidth - $textWidth) / 2);
            $pointTop = $imgHeight - $textLength * $lineHeight - 20;
        } elseif ($point == 9) { //右下部
            $porintLeft = $imgWidth - $textWidth - 20;
            $pointTop = $imgHeight - $textLength * $lineHeight - 20;
        }

        //如果有angle旋转角度，则重新设置 top ,left 坐标值
        if ($angle != 0) {
            if ($angle < 90) {
                $diffTop = ceil(sin($angle * M_PI / 180) * $textWidth);

                if (in_array($point, array(1, 2, 3))) {// 上部 top 值增加
                    $pointTop += $diffTop;
                } elseif (in_array($point, array(4, 5, 6))) {// 中部 top 值根据图片总高判断
                    if ($textWidth > ceil($imgHeight / 2)) {
                        $pointTop += ceil(($textWidth - $imgHeight / 2) / 2);
                    }
                }
            } elseif ($angle > 270) {
                $diffTop = ceil(sin((360 - $angle) * M_PI / 180) * $textWidth);

                if (in_array($point, array(7, 8, 9))) {// 上部 top 值增加
                    $pointTop -= $diffTop;
                } elseif (in_array($point, array(4, 5, 6))) {// 中部 top 值根据图片总高判断
                    if ($textWidth > ceil($imgHeight / 2)) {
                        $pointTop = ceil(($imgHeight - $diffTop) / 2);
                    }
                }
            }
        }
        /*   原方法
        foreach ($text as $key => $val) {
            imagettftext($im, $fontSize, $angle, $porintLeft, $pointTop + $key * $lineHeight, $text_color, $fontUrl, $val);
        }       */
        //根据像素定义水印文字的位置
        foreach ($text as $key => $val) {

            $arr = explode('#', $val);
            foreach ($arr as $k => $v) {
                if($k == 0) {
                    imagettftext($im, $fontSize, $angle, $porintLeft, $pointTop + $key * $lineHeight, $text_color, $fontUrl, $v);
                } elseif($k == 1) {
                    imagettftext($im, $fontSize, $angle, $porintLeft+300, $pointTop + $key * $lineHeight, $text_color, $fontUrl, $v);
                } elseif($k ==2 ){
                    imagettftext($im, $fontSize, $angle, $porintLeft+550, $pointTop + $key * $lineHeight, $text_color, $fontUrl, $v);
                }

            }

        }

        // 输出图像
        $imageOutputFun($im, $newimgurl, 9);
        // 释放内存
        imagedestroy($im);
//        return $newimgurl;

        $pimg       = Common::pimgClientV2(1);
        $uploadResp = $pimg->upload($newimgurl,array('convert' => 'N'));
        $saveResp = $pimg->saveToAttachment(
            $uploadResp['data']['iid'],
            [
                'type' => 'mingxi',
                'tag' => '交易明细图',
                'from' => 'backend',
                'status' => 'on'
            ]
        );

        $temp = $uploadResp['data']['img_url'];
        $aid  = $saveResp['data']['aid'];
        $res = resultSuccess([
            'pic' => $temp,
            'aid' => $aid,
        ]);
        unlink($newimgurl);
        return $res;

    }
}