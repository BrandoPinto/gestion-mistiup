/** Enlace wa.me. Los celulares peruanos de 9 dígitos se completan con el código de país 51. */
export function whatsappUrl(number: string): string {
    const digits = number.replace(/\D/g, '');
    const international = digits.length === 9 && digits.startsWith('9') ? `51${digits}` : digits;

    return `https://wa.me/${international}`;
}

/** "987654321" → "987 654 321"; otros formatos se muestran tal cual. */
export function formatPhone(number: string): string {
    const digits = number.replace(/\D/g, '');

    if (digits.length === 9 && !number.startsWith('+')) {
        return `${digits.slice(0, 3)} ${digits.slice(3, 6)} ${digits.slice(6)}`;
    }

    return number;
}
