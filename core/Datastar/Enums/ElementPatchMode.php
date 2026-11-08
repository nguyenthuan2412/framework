<?php

namespace Ryra\Core\Datastar\Enums;

/**
 * Cách Datastar gắn HTML nhận được vào DOM.
 *
 * Hai mode `outer` và `inner` dùng morph nên giữ nguyên trạng thái của các
 * element cũ (focus, giá trị input...), các mode còn lại thay thẳng nên trạng
 * thái bị reset.
 */
enum ElementPatchMode: string
{
    /** Morph đè lên chính element khớp selector (mặc định) */
    case Outer = 'outer';

    /** Morph vào phần nội dung bên trong element */
    case Inner = 'inner';

    /** Thay thế element, không morph */
    case Replace = 'replace';

    /** Chèn vào đầu phần nội dung bên trong element */
    case Prepend = 'prepend';

    /** Chèn vào cuối phần nội dung bên trong element */
    case Append = 'append';

    /** Chèn ngay trước element */
    case Before = 'before';

    /** Chèn ngay sau element */
    case After = 'after';

    /** Gỡ element khỏi DOM */
    case Remove = 'remove';

    /**
     * Nhận cả enum lẫn string để controller viết `mode: 'append'` cho gọn.
     */
    public static function make(self|string $mode): self
    {
        if ($mode instanceof self) {
            return $mode;
        }

        return self::tryFrom($mode) ?? throw new \InvalidArgumentException(
            \sprintf(
                'Element patch mode "%s" không hợp lệ, chỉ nhận: %s',
                $mode,
                \implode(', ', \array_column(self::cases(), 'value'))
            )
        );
    }

    public function isDefault(): bool
    {
        return $this === self::Outer;
    }
}
