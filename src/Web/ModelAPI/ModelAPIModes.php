<?php

namespace Cube\Web\ModelAPI;

enum ModelAPIModes: string
{
    case CREATE = 'create';
    case READ = 'read';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
