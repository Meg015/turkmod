// Loopback-only SMTP sink. It never forwards messages or contacts another server.
const net = require('node:net');
const fs = require('node:fs');
const capturePath = process.argv[2];
const server = net.createServer(socket => {
    socket.setEncoding('utf8');
    socket.setTimeout(15000, () => socket.destroy());
    socket.on('error', () => {});
    let pending = '', data = null, recipient = '';
    socket.write('220 notification-regression ESMTP\r\n');
    socket.on('data', chunk => {
        pending += chunk;
        while (pending.includes('\n')) {
            const end = pending.indexOf('\n');
            const line = pending.slice(0, end).replace(/\r$/, '');
            pending = pending.slice(end + 1);
            if (data !== null) {
                if (line === '.') {
                    if (recipient.includes('fail-data@')) {
                        socket.write('451 Temporary fixture failure\r\n');
                    } else {
                        fs.appendFileSync(capturePath, JSON.stringify({ recipient, message: data.join('\r\n') }) + '\n');
                        socket.write('250 Message accepted by fixture\r\n');
                    }
                    data = null;
                } else {
                    data.push(line.replace(/^\.\./, '.'));
                }
            } else if (/^EHLO|^HELO/i.test(line)) {
                socket.write('250 notification-regression\r\n');
            } else if (/^MAIL FROM:/i.test(line)) {
                socket.write('250 Sender accepted\r\n');
            } else if (/^RCPT TO:/i.test(line)) {
                recipient = line;
                socket.write(recipient.includes('reject@') ? '550 Recipient rejected by fixture\r\n' : '250 Recipient accepted\r\n');
            } else if (line === 'DATA') {
                data = [];
                socket.write('354 End with a dot\r\n');
            } else if (line === 'QUIT') {
                socket.end('221 Goodbye\r\n');
            } else {
                socket.write('250 OK\r\n');
            }
        }
    });
});
server.listen(0, '127.0.0.1', () => process.stdout.write(JSON.stringify({ port: server.address().port }) + '\n'));
