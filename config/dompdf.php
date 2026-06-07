<?php
return [
    
    'show_warnings' => true,
    'show_errors' => true,
    'show_info' => true,
    'show_font_metrics' => false,
    'enable_font_subsetting' => true,
    'enable_remote' => true,
    'enable_php' => false,
    'enable_javascript' => false,
    'enable_css_float' => true,
    'enable_html5_parser' => true,
    'default_media_type' => 'print',
    'default_paper_size' => 'a4',
'default_font' => 'dejavu sans',
    'default_font_size' => 16,
    'dpi' => 96,
    'font_dir' => storage_path('fonts/'),
    'font_cache' => storage_path('fonts/'),
    'temp_dir' => sys_get_temp_dir(),
    'isPhpEnabled' => true,
'isRemoteEnabled' => true, // لتحميل الصور عن بعد إن وجدت
'chroot' => realpath(base_path()),
];






















