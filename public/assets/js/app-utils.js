function formatTime(totalMinutes) {
    const normalized = ((totalMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
    const hours = Math.floor(normalized / 60);
    const minutes = normalized % 60;
    return hours.toString().padStart(2, "0") + ":" + minutes.toString().padStart(2, "0");
}

function parseTime(timeStr) {
    if (!timeStr) return 0;
    const parts = timeStr.split(":");
    const hours = parseInt(parts[0], 10) || 0;
    const minutes = parseInt(parts[1], 10) || 0;
    return hours * 60 + minutes;
}
