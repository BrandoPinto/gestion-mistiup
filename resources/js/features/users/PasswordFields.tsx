import { Field, Input } from '@/components/ui/Field';

type PasswordFieldsProps = {
    password: string;
    confirmation: string;
    onPasswordChange: (value: string) => void;
    onConfirmationChange: (value: string) => void;
    error?: string;
    label?: string;
};

export const PASSWORD_HINT = 'Mínimo 10 caracteres, con letras y números.';

/** Contraseña + confirmación. El navegador no debe autocompletarla con la del administrador. */
export function PasswordFields({ password, confirmation, onPasswordChange, onConfirmationChange, error, label = 'Nueva contraseña' }: PasswordFieldsProps) {
    return (
        <>
            <Field label={label} error={error} hint={PASSWORD_HINT} required>
                {({ id, invalid, describedBy }) => (
                    <Input id={id} type="password" value={password} onChange={(event) => onPasswordChange(event.target.value)} invalid={invalid} aria-describedby={describedBy} autoComplete="new-password" />
                )}
            </Field>
            <Field label="Repetir contraseña" required>
                {({ id }) => <Input id={id} type="password" value={confirmation} onChange={(event) => onConfirmationChange(event.target.value)} autoComplete="new-password" />}
            </Field>
        </>
    );
}
