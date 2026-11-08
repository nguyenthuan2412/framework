<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cài đặt chung
    |--------------------------------------------------------------------------
    |
    | Đặt một số giá trị mặc định. Có thể thêm tất cả các hằng số được định nghĩa
    | trong file dompdf_config.inc.php. Bạn cũng có thể ghi đè toàn bộ file cấu hình.
    |
    */
    'show_warnings' => false,   // Ném Exception nếu có cảnh báo từ dompdf

    'public_path' => null,  // Ghi đè đường dẫn public nếu cần

    /*
     * Font Dejavu Sans thiếu ký tự cho các entity chuyển đổi, tắt nếu cần hiển thị ký hiệu € hoặc £.
     */
    'convert_entities' => true,

    'options' => [

        /**
         * Thư mục lưu trữ font của DOMPDF
         *
         * Thư mục này là nơi DOMPDF lưu trữ font và các thông tin về font.
         * Lưu ý: Thư mục phải tồn tại và có quyền ghi bởi tiến trình webserver.
         * *Nhớ có dấu gạch chéo ở cuối đường dẫn.*
         *
         * Ghi chú:
         * Có thể thêm font mới bằng cách chạy file load_font.php từ dòng lệnh.
         *
         * Chỉ có 14 font cơ bản ("Base 14 fonts") là được hỗ trợ trên mọi trình xem PDF.
         * Các font khác phải được nhúng vào file PDF, nếu không sẽ hiển thị sai.
         * Việc nhúng thêm font có thể làm tăng kích thước file.
         *
         * Các font mặc định gồm:
         * Courier, Courier-Bold, Courier-BoldOblique, Courier-Oblique,
         * Helvetica, Helvetica-Bold, Helvetica-BoldOblique, Helvetica-Oblique,
         * Times-Roman, Times-Bold, Times-BoldItalic, Times-Italic,
         * Symbol, ZapfDingbats.
         */
        'font_dir' => storage_path('fonts'), // khuyến nghị bởi dompdf

        /**
         * Thư mục cache cho font
         *
         * Chứa các dữ liệu cache của font đã dùng.
         * Thư mục này phải có quyền ghi và có thể trùng với font_dir.
         */
        'font_cache' => storage_path('fonts'),

        /**
         * Thư mục tạm
         *
         * Thư mục này cần quyền ghi bởi webserver.
         * DOMPDF dùng để tải ảnh từ xa hoặc xử lý tạm khi render PDF.
         */
        'temp_dir' => sys_get_temp_dir(),

        /**
         * ==== QUAN TRỌNG ====
         *
         * "chroot" của dompdf: Giới hạn quyền truy cập của dompdf để tránh truy cập
         * vào file hệ thống. Mọi file được dompdf đọc phải nằm trong thư mục con của đường dẫn này.
         * KHÔNG được đặt là '/', vì điều này có thể cho phép hacker đọc bất kỳ file nào trên server.
         */
        'chroot' => realpath(base_path()),

        /**
         * Danh sách giao thức được phép
         *
         * Cho phép các loại URI mà dompdf có thể tải nội dung từ đó.
         */
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        /**
         * Kiểm tra đường dẫn hợp lệ cho file log và file tạm
         */
        'artifactPathValidation' => null,

        /**
         * File log đầu ra
         */
        'log_output_file' => null,

        /**
         * Có bật chế độ chia nhỏ font hay không (font subsetting)
         */
        'enable_font_subsetting' => false,

        /**
         * Backend render PDF
         *
         * Các lựa chọn hợp lệ: 'PDFLib', 'CPDF', 'GD', hoặc 'auto'.
         * - 'auto': tự động chọn PDFLib nếu có, nếu không thì dùng CPDF.
         * - 'GD': xuất ra file hình ảnh thay vì PDF.
         *
         * 'CPDF' là backend mặc định, đủ dùng cho hầu hết các trường hợp.
         */
        'pdf_backend' => 'CPDF',

        /**
         * Kiểu media mặc định khi render
         *
         * screen, print, tv, handheld, all, ...
         * Dùng để chọn cách hiển thị CSS theo loại thiết bị.
         */
        'default_media_type' => 'screen',

        /**
         * Kích thước giấy mặc định
         *
         * Bắc Mỹ: "letter"
         * Các nước khác: "a4"
         */
        'default_paper_size' => 'a4',

        /**
         * Hướng giấy mặc định (dọc hoặc ngang)
         */
        'default_paper_orientation' => 'landscape',

        /**
         * Font mặc định
         *
         * Dùng khi không tìm thấy font phù hợp. Font này phải tồn tại trong thư mục font.
         */
        'default_font' => 'serif',

        /**
         * DPI (độ phân giải hình ảnh)
         *
         * Dùng để tính độ sắc nét của ảnh và chữ. Mặc định 96 DPI.
         */
        'dpi' => 96,

        /**
         * Cho phép nhúng PHP vào file HTML
         *
         * Nếu bật (true), dompdf sẽ chạy code PHP trong thẻ:
         * <script type="text/php"> ... </script>
         *
         * ⚠️ Cảnh báo: Chỉ bật nếu bạn tin tưởng nội dung HTML. Có thể gây rủi ro bảo mật.
         */
        'enable_php' => false,

        /**
         * Cho phép nhúng JavaScript
         *
         * Cho phép các đoạn <script type="text/javascript"> ... </script>
         * được nhúng vào file PDF (chạy trên trình xem PDF, không phải trong trình duyệt).
         */
        'enable_javascript' => true,

        /**
         * Cho phép tải file từ xa (hình ảnh, CSS, v.v.)
         *
         * ⚠️ Cảnh báo: Bật tùy chọn này có thể gây rủi ro nếu xử lý nội dung HTML từ bên ngoài.
         */
        'enable_remote' => true,

        /**
         * Danh sách host được phép tải file từ xa
         *
         * Nếu enable_remote = false thì tùy chọn này bị bỏ qua.
         * Nếu = null thì cho phép tất cả host.
         */
        'allowed_remote_hosts' => null,

        /**
         * Tỉ lệ chiều cao font so với line-height của trình duyệt
         */
        'font_height_ratio' => 1.1,

        /**
         * Bật parser HTML5
         *
         * (Tính năng này luôn bật trong dompdf 2.x)
         */
        'enable_html5_parser' => true,
    ],
];
