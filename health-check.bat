@echo off
echo ===============================
echo Checking critical fixes...
echo ===============================

echo.
echo 1) Checking Customer pagination...
findstr /C:"paginate(" app\Http\Controllers\Admin\CustomerController.php

echo.
echo 2) Checking Form slug validation...
findstr /C:"slug" app\Http\Controllers\Admin\FormController.php

echo.
echo 3) Checking FormField name save...
findstr /C:"name" app\Http\Controllers\Admin\FormFieldController.php

echo.
echo 4) Checking reorder route...
findstr /C:"forms.fields.reorder" routes\web.php

echo.
echo 5) Checking admin routes...
php artisan route:list | findstr admin

echo.
echo ===============================
echo Done.
pause
