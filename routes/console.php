<?php

use Illuminate\Support\Facades\Schedule;

// Schedule audit log cleanup to run daily at midnight
Schedule::command('audit:cleanup')->daily();
