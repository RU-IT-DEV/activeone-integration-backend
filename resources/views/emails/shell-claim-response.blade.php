@component('mail::message', ['logoUrl' => $logoUrl])

# Claim Update

Dear **{{ ucwords(strtolower($claimResponse->claim->member->first_name.' '.$claimResponse->claim->member->last_name)) }}**,

---

@if($status == 'Approved')

Your **{{ ucwords(strtolower($claim_type)) }} Claim #{{ $claimId }}** has been **Approved**.

Attached below is your envelope label.

### Instructions:
1. Print the **Envelope Label**
2. Attach it securely on a brown envelope (QR visible)
3. Insert hard copies of receipts

@if($claim_type == 'choicepot')
4. Do not combine multiple claim numbers
@endif

5. Submit to:
- SBO Manila (Makati Office)
- Shell Pilipinas Corporation (Taguig Office)

@if($claim_type == 'choicepot')
You will receive an email from **RCBC Online-Corporate Admin** near the 15th of the month.  
Please allow **24–48 hours** for crediting.
@endif

---

@elseif($status == 'Partially approved')

We approved **Php {{ number_format($approvedAmount, 2) }}** for your  
**{{ ucwords(strtolower($claim_type)) }} Claim #{{ $claimId }}**.

### Notes:
{{ ltrim($claimResponse->remarks ?? '---') }}

Attached below is your envelope label.

---

@else

Your **{{ ucwords(strtolower($claim_type)) }} Claim #{{ $claimId }}** has been **Rejected**.

**Reason:** {{ $claimResponse->rejection_reason ?? '---' }}

### Remarks:
{{ ltrim($claimResponse->remarks ?? '---') }}

---

@endif

For inquiries, call **09178534431** or email:

@if($claim_type == 'fsa')
**shell_fsa@flexicare.com.ph**
@elseif($claim_type == 'reimbursement')
**shell_reimbursement@flexicare.com.ph**
@else
**shell_choicepot@flexicare.com.ph**
@endif

---

### Help us improve our service
[Click here for the Survey](https://forms.office.com/pages/responsepage.aspx?id=UzTI9rjtt0SGOW0JDgEaY771u3FoQmZFn6Mr_hSkSYNUNDNFNVY4T0VVTFNFVDRaUjFKTVhSUDRMTy4u&route=shorturl)

---

Thanks,<br>
**Flexicare Support Team**

@endcomponent