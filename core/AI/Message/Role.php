<?php

namespace Ryra\Core\AI\Message;

enum Role: string
{
    case System = 'system';

    case User = 'user';

    case Assistant = 'assistant';

    case Tool = 'tool';
}
