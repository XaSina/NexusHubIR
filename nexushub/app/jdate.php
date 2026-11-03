<?php
/**
 * تبدیل تاریخ میلادی ↔ شمسی و فرمت‌دهی تاریخ شمسی
 */

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * intdiv($days, 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = (($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0);
    $sal_a = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

function jalali_month_names(): array
{
    return [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
}

function fa_num($str): string
{
    return strtr((string) $str, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function en_num($str): string
{
    return strtr((string) $str, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

/**
 * فرمت شبیه date() ولی شمسی
 * Y سال | y دو رقمی | m ماه دو رقمی | n ماه | d روز دو رقمی | j روز | F نام ماه | l نام روز
 * H ساعت ۲۴ | G ساعت بدون صفر | i دقیقه | s ثانیه | a ق.ظ/ب.ظ
 * @param int|string|null $time تایم‌استمپ یا رشته‌ی تاریخ
 */
function jdate(string $format, $time = null, bool $persianDigits = true): string
{
    if ($time === null || $time === '') $ts = time();
    elseif (is_numeric($time)) $ts = (int) $time;
    else $ts = strtotime((string) $time) ?: time();

    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    $weekdays = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    $months = jalali_month_names();

    $out = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        $c = $format[$i];
        if ($c === '\\' && $i + 1 < $len) {
            $out .= $format[++$i];
            continue;
        }
        switch ($c) {
            case 'Y': $out .= $jy; break;
            case 'y': $out .= substr((string) $jy, -2); break;
            case 'm': $out .= str_pad((string) $jm, 2, '0', STR_PAD_LEFT); break;
            case 'n': $out .= $jm; break;
            case 'd': $out .= str_pad((string) $jd, 2, '0', STR_PAD_LEFT); break;
            case 'j': $out .= $jd; break;
            case 'F': case 'M': $out .= $months[$jm]; break;
            case 'l': case 'D': $out .= $weekdays[(int) date('w', $ts)]; break;
            case 'H': $out .= date('H', $ts); break;
            case 'G': $out .= date('G', $ts); break;
            case 'i': $out .= date('i', $ts); break;
            case 's': $out .= date('s', $ts); break;
            case 'a': $out .= ((int) date('G', $ts) < 12) ? 'ق.ظ' : 'ب.ظ'; break;
            default: $out .= $c;
        }
    }
    return $persianDigits ? fa_num($out) : $out;
}

/** «۵ دقیقه پیش» */
function jago($time): string
{
    $ts = is_numeric($time) ? (int) $time : strtotime((string) $time);
    $diff = time() - $ts;
    if ($diff < 0) return jdate('j F Y', $ts);
    if ($diff < 60) return 'لحظاتی پیش';
    if ($diff < 3600) return fa_num((string) intdiv($diff, 60)) . ' دقیقه پیش';
    if ($diff < 86400) return fa_num((string) intdiv($diff, 3600)) . ' ساعت پیش';
    if ($diff < 86400 * 7) return fa_num((string) intdiv($diff, 86400)) . ' روز پیش';
    return jdate('j F Y', $ts);
}

/**
 * رشته‌ی شمسی مانند «1405/08/12 14:30» را به DATETIME میلادی تبدیل می‌کند
 */
function jalali_to_datetime(string $str): ?string
{
    $str = trim(en_num($str));
    if (!preg_match('~^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$~', $str, $m)) {
        return null;
    }
    [$gy, $gm, $gd] = jalali_to_gregorian((int) $m[1], (int) $m[2], (int) $m[3]);
    $h = isset($m[4]) ? (int) $m[4] : 0;
    $i = isset($m[5]) ? (int) $m[5] : 0;
    if (!checkdate($gm, $gd, $gy) || $h > 23 || $i > 59) return null;
    return sprintf('%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, $h, $i);
}

/** DATETIME میلادی → «1405/08/12 14:30» با ارقام لاتین برای فیلدهای فرم */
function datetime_to_jalali_input(?string $dt, bool $withTime = true): string
{
    if (!$dt) return '';
    return jdate($withTime ? 'Y/m/d H:i' : 'Y/m/d', $dt, false);
}
