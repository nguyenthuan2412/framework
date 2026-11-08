<?php

namespace Ryra\Core\Datastar;

/**
 * Các hằng số theo SDK spec của Datastar.
 *
 * @see https://github.com/starfederation/datastar/blob/main/sdk/ADR.md
 */
final class Consts
{
    /**
     * Thời gian reconnect mặc định của client (ms). Chỉ ghi dòng `retry`
     * vào event khi giá trị khác hằng số này.
     */
    public const DEFAULT_SSE_RETRY_DURATION = 1000;

    /**
     * Query param chứa signals của request GET.
     */
    public const DATASTAR_KEY = 'datastar';

    /**
     * Datastar gắn header này vào mọi request do nó phát ra.
     */
    public const DATASTAR_REQUEST_HEADER = 'datastar-request';

    /**
     * Header client dùng để báo nó cần server render những fragment nào.
     * Không thuộc spec của Datastar, do component này quy ước.
     */
    public const FRAGMENTS_HEADER = 'datastar-fragments';

    /**
     * Query param thay thế cho header ở trên.
     */
    public const FRAGMENTS_PARAM = '_fragments';

    /**
     * Mode patch elements mặc định.
     */
    public const DEFAULT_ELEMENT_PATCH_MODE = 'outer';

    /**
     * Chỉ patch signal khi signal chưa tồn tại.
     */
    public const DEFAULT_PATCH_SIGNALS_ONLY_IF_MISSING = false;

    /**
     * Bật hiệu ứng view transition khi patch.
     */
    public const DEFAULT_ELEMENTS_USE_VIEW_TRANSITIONS = false;

    /**
     * Thuộc tính tự gỡ thẻ script sau khi chạy xong.
     */
    public const DEFAULT_EXECUTE_SCRIPT_AUTO_REMOVE_ATTRIBUTE = 'data-effect="el.remove()"';

    /**
     * Selector mặc định khi chèn script vào tài liệu.
     */
    public const DEFAULT_EXECUTE_SCRIPT_SELECTOR = 'body';

    /**
     * Các key dữ liệu của event, dùng làm tiền tố cho dòng `data:`.
     */
    public const SELECTOR_DATALINE_LITERAL = 'selector';
    public const MODE_DATALINE_LITERAL = 'mode';
    public const ELEMENTS_DATALINE_LITERAL = 'elements';
    public const NAMESPACE_DATALINE_LITERAL = 'namespace';
    public const USE_VIEW_TRANSITION_DATALINE_LITERAL = 'useViewTransition';
    public const VIEW_TRANSITION_SELECTOR_DATALINE_LITERAL = 'viewTransitionSelector';
    public const SIGNALS_DATALINE_LITERAL = 'signals';
    public const ONLY_IF_MISSING_DATALINE_LITERAL = 'onlyIfMissing';
}
