<?php

namespace App\Constants;

class AppConstants
{
    // Pagination
    public const DEFAULT_PAGINATION = 15;
    public const SEARCH_RESULT_LIMIT = 10;
    
    // File upload limits
    public const MAX_FILE_SIZE = 25600; // 25MB in KB
    public const ALLOWED_IMAGE_TYPES = ['jpeg', 'png', 'jpg', 'gif'];
    
    // Status values
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    
    // Roles
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_CLIENT = 'customer';
    
    // Service frequencies
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BI_WEEKLY = 'bi-weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_AS_NEEDED = 'as-needed';
    
    // Pool types
    public const POOL_TYPE_FIBERGLASS = 'fiberglass';
    public const POOL_TYPE_VINYL_LINER = 'vinyl_liner';
    public const POOL_TYPE_CONCRETE = 'concrete';
    public const POOL_TYPE_GUNITE = 'gunite';
    
    // Water types
    public const WATER_TYPE_CHLORINE = 'chlorine';
    public const WATER_TYPE_SALT = 'salt';
    
    // Access types
    public const ACCESS_RESIDENTIAL = 'residential';
    public const ACCESS_COMMERCIAL = 'commercial';
    
    // Settings
    public const SETTING_INDOOR = 'indoor';
    public const SETTING_OUTDOOR = 'outdoor';
    
    // Installation types
    public const INSTALLATION_INGROUND = 'inground';
    public const INSTALLATION_ABOVE = 'above';
    
    // Service report types
    public const REPORT_TYPE_FULL = 'full';
    public const REPORT_TYPE_INVOICE_ONLY = 'invoice_only';
    public const REPORT_TYPE_SERVICES_ONLY = 'services_only';
    public const REPORT_TYPE_NONE = 'none';
} 