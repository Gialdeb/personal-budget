<x-mail::message>
# {{ __('admin_invitation.mail_subject') }}

{{ __('admin_invitation.mail_intro') }}

**{{ $otp }}**

{{ __('admin_invitation.mail_expiry') }}

<x-mail::button :url="route('admin-invitations.show', $invitationUuid)">
{{ __('admin_invitation.confirm') }}
</x-mail::button>

{{ __('admin_invitation.mail_ignore') }}
</x-mail::message>
