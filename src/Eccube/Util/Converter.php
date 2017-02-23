<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Eccube\Util;

/**
 * Class Converter
 *
 * @package Eccube\Util
 */
class Converter
{
    /**
     * Convert all character to katakana
     *
     * @link src/Eccube/EventListener/ConvertKanaListener.php:24
     * @param mixed  $data
     * @param string $option
     * @param string $encoding
     *
     * @return string
     */
    public static function convertToKana($data, $option = 'CV', $encoding = 'utf-8')
    {
        if (is_array($data)) {
            foreach ($data as &$value) {
                $value = mb_convert_kana($value, $option, $encoding);
            }
        } else {
            $data = mb_convert_kana($data, $option, $encoding);
        }

        return $data;
    }
}
