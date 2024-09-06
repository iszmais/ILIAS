const crypto = require('crypto');
const ece = require('http_ece');

const localCurve = crypto.createECDH('prime256v1');
const localPublicKey = localCurve.generateKeys();

const res = ece.encrypt(Buffer.from(process.argv[4] ?? ''), {
  version: 'aes128gcm',
  dh: process.argv[2],
  privateKey: localCurve,
  salt: crypto.randomBytes(16).toString('base64url'),
  authSecret: process.argv[3],
});

process.stdout.write(res);
