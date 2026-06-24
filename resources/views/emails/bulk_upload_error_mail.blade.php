@component('mail::message', ['logoUrl' => $logoUrl])
# Bulk Upload Completed With Errors

Your bulk member upload has **completed**, but some rows could not be processed.

### Summary
- ❌ **Failed rows:** {{ count($errorRows) }}

You can download a CSV file containing the failed rows and validation errors using the link below.

@component('mail::button', ['url' => $publicUrl, 'color' => 'error'])
Download Error File (CSV)
@endcomponent

> **Note:**  
> The download link will expire after **15 days**.  
> You may correct the errors and re-upload the file once fixed.

If you need assistance interpreting the errors, please contact our support team.

Thanks,<br>
**Flexicare Support Team**
@endcomponent