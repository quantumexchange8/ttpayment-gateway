const formatDateTime = (date, includeTime=true) => {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const formattedDate = new Date(date);

    const day = formattedDate.getDate().toString().padStart(2, '0');
    const month = months[formattedDate.getMonth()];
    const year = formattedDate.getFullYear();
    const hours = formattedDate.getHours().toString().padStart(2, '0');
    const minutes = formattedDate.getMinutes().toString().padStart(2, '0');
    const seconds = formattedDate.getSeconds().toString().padStart(2, '0');

    if (includeTime) {
        return `${day} ${month} ${year} ${hours}:${minutes}:${seconds}`;
    } else {
        return `${day} ${month} ${year}`;
    }
}

const formatDate = (date, includeTime=false) => {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const formattedDate = new Date(date);

    const day = formattedDate.getDate().toString().padStart(2, '0');
    const month = months[formattedDate.getMonth()];
    const year = formattedDate.getFullYear();
    const hours = formattedDate.getHours().toString().padStart(2, '0');
    const minutes = formattedDate.getMinutes().toString().padStart(2, '0');
    const seconds = formattedDate.getSeconds().toString().padStart(2, '0');

    if (includeTime) {
        return `${day} ${month} ${year} ${hours}:${minutes}:${seconds}`;
    } else {
        return `${day} ${month} ${year}`;
    }
}

const formatAmount = (amount) => {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

export {
    formatDateTime, 
    formatDate,
    formatAmount
};