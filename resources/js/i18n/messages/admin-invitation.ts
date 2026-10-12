export const adminInvitationMessages = {
    it: {
        title: 'Nomina amministratore',
        pending: 'In attesa di conferma',
        expired: 'Invito scaduto',
        admin: 'Amministratore',
        description:
            'Invieremo un codice a {email}. L’utente acquisirà accesso all’area amministrativa solo dopo averlo confermato personalmente.',
        resend: 'Reinvia codice',
        cancel: 'Annulla invito',
        cancel_description:
            'Il codice inviato non sarà più valido. L’utente manterrà i permessi attuali.',
        send: 'Invia invito',
        cancel_button: 'Annulla',
        sent: 'Invito inviato. Nessun privilegio è stato ancora assegnato.',
        cancelled: 'Invito annullato.',
        accepted: 'Nomina confermata. Ora sei amministratore.',
        ineligible:
            'Puoi invitare soltanto un utente attivo, con email verificata e non già amministratore.',
        rate_limited:
            'Attendi almeno un minuto prima di reinviare. Massimo cinque codici all’ora per destinatario.',
        invalid:
            'Codice errato, scaduto o non più valido. Dopo cinque tentativi serve un nuovo invito.',
        confirm: 'Conferma nomina',
        user_intro:
            'Inserisci il codice ricevuto via email per diventare amministratore di Soamco. Confermando acquisirai accesso alla gestione degli utenti e delle impostazioni amministrative.',
        otp: 'Codice a 6 cifre',
        unavailable:
            'Questo invito non è più disponibile. Chiedi all’amministratore un nuovo codice.',
        expires: 'Il codice scade il {date}.',
        mail_subject: 'Soamco — Invito come amministratore',
        mail_intro:
            'Un amministratore Soamco ha richiesto la tua nomina come amministratore. Usa questo codice nel tuo account per confermare personalmente l’operazione:',
        mail_expiry:
            'Il codice scade 15 minuti dopo la richiesta. Devi accedere all’account destinatario per usarlo.',
        mail_ignore:
            'Se non riconosci la richiesta, puoi ignorare questa email: i tuoi permessi non cambieranno.',
        last_admin:
            'Non puoi eliminare l’ultimo amministratore. Nomina prima un altro amministratore e attendi la sua conferma.',
    },
    en: {
        title: 'Appoint administrator',
        pending: 'Awaiting confirmation',
        expired: 'Invitation expired',
        admin: 'Administrator',
        description:
            'We will send a code to {email}. This user will gain access to administration only after confirming it personally.',
        resend: 'Send a new code',
        cancel: 'Cancel invitation',
        cancel_description:
            'The previous code will no longer work. The user will keep their current permissions.',
        send: 'Send invitation',
        cancel_button: 'Cancel',
        sent: 'Invitation sent. No privileges have been assigned yet.',
        cancelled: 'Invitation cancelled.',
        accepted: 'Appointment confirmed. You are now an administrator.',
        ineligible:
            'Only active users with a verified email who are not already administrators can be invited.',
        rate_limited:
            'Wait at least one minute before resending. Up to five codes per hour per recipient.',
        invalid:
            'Incorrect, expired or invalid code. After five attempts a new invitation is required.',
        confirm: 'Confirm appointment',
        user_intro:
            'Enter the code received by email to become a Soamco administrator. Confirming grants access to user management and administrative settings.',
        otp: '6-digit code',
        unavailable:
            'This invitation is no longer available. Ask an administrator for a new code.',
        expires: 'The code expires on {date}.',
        mail_subject: 'Soamco — Administrator invitation',
        mail_intro:
            'A Soamco administrator has requested your appointment as administrator. Use this code in your account to personally confirm the operation:',
        mail_expiry:
            'The code expires 15 minutes after the request. You must sign in to the recipient account to use it.',
        mail_ignore:
            'If you do not recognise this request, ignore this email: your permissions will not change.',
        last_admin:
            'You cannot delete the last administrator. First invite another administrator and wait for their confirmation.',
    },
};
