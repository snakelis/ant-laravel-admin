<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // 使用本地模型便于绑定 Policy 和 API Resource。
}
