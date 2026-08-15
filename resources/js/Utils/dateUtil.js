// const formatDate = (d) => d ? new Date(d).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'



// export const formatDate = (d) => {
//     if (!d) return '—';
//     const date = new Date(d);
//     const day = String(date.getDate()).padStart(2, '0');
//     const month = String(date.getMonth() + 1).padStart(2, '0');
//     const year = date.getFullYear();
//     return `${day}/${month}/${year}`;
// }

export const formatDate = (d, withTime = false) => {
    if (!d) return '—';
    const m = String(d).match(/(\d{4})-(\d{2})-(\d{2})[ T]?(\d{2})?:?(\d{2})?/);
    if (!m) return '—';
    const [, year, month, day, hour = '00', min = '00'] = m;
    return withTime ? `${day}/${month}/${year} ${hour}:${min}` : `${day}/${month}/${year}`;
}


export function getExpirationStatus(fecha) {
    const target = new Date(String(fecha).replace(' ', 'T'))
    const diffMs = target - new Date()

    if (isNaN(target.getTime())) return { text: 'Fecha inválida', color: 'muted' }
    if (diffMs <= 0) return { text: 'Expirado', color: 'danger' }

    const horas = diffMs / 3.6e6

    if(horas < 1) {
        const m = Math.ceil(horas * 60)
        return { text: `Quedan ${m} minuto${m === 1 ? '' : 's'}`, color: 'danger' }
    }

    if (horas < 24) {
        const h = Math.ceil(horas)
        return { text: `Quedan ${h} hora${h === 1 ? '' : 's'}`, color: horas < 3 ? 'danger' : 'warning' }
    }

    const dias = Math.floor(horas / 24)
    return { text: `Quedan ${dias} día${dias === 1 ? '' : 's'}`, color: 'success' }
}
