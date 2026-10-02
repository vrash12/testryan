import test from 'node:test';
import assert from 'node:assert/strict';
import { fileProblem, uploadError } from '../../resources/js/uploads.js';

test('file selection boundaries and unsupported phone formats', () => {
    assert.equal(fileProblem({name: 'photo.jpg', type: 'image/jpeg', size: 10485760}), null);
    assert.match(fileProblem({name: 'photo.jpg', type: 'image/jpeg', size: 10485761}), /10 MB/);
    assert.match(fileProblem({name: 'camera.HEIC', type: '', size: 10}), /Export.*JPEG/);
    assert.match(fileProblem({name: 'photo.png', type: 'image/heif', size: 10}), /HEIC/);
    assert.equal(fileProblem({name: 'scan.pdf', type: '', size: 500}), null);
    assert.match(fileProblem({name: 'scan.pdf', type: 'text/html', size: 500}), /JPEG, PNG or PDF/);
    assert.match(fileProblem({name: 'empty.png', type: 'image/png', size: 0}), /empty/);
});

test('connection failures never imply success and server details stay private', () => {
    for (const status of [0, 200, 500, 502]) assert.match(uploadError(status, {message: 'private stack trace'}), /not confirmed/);
    assert.match(uploadError(419), /session expired/);
    assert.match(uploadError(403), /access changed/);
    assert.match(uploadError(409), /reference/);
    assert.match(uploadError(429), /Wait a minute/);
    assert.equal(uploadError(422, {errors: {'media.0': ['File is too large.']}}), 'File is too large.');
});
