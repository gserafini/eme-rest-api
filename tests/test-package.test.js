'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const os = require('node:os');

test('release ZIP uses this checkout even when its directory has a different name', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'eme-package-'));
  const checkout = path.join(root, 'review-worktree');
  fs.mkdirSync(checkout);
  const php = '<?php /* Version: 1.9.2 */\n';
  fs.writeFileSync(path.join(checkout, 'eme-rest-api.php'), php);
  fs.writeFileSync(path.join(checkout, 'readme.txt'), 'Stable tag: 1.9.2\n');
  fs.writeFileSync(path.join(checkout, 'README.md'), '# Package fixture\n');
  fs.copyFileSync(path.join(__dirname, '..', 'create-zip.sh'), path.join(checkout, 'create-zip.sh'));
  try {
    cp.execFileSync('bash', [path.join(checkout, 'create-zip.sh')], {encoding:'utf8'});
    const zip = path.join(checkout, 'eme-rest-api.zip');
    assert.ok(fs.existsSync(zip), 'The ZIP must be created in the requesting checkout');
    assert.equal(cp.execFileSync('unzip', ['-p', zip, 'eme-rest-api/eme-rest-api.php'], {encoding:'utf8'}), php);
    assert.deepEqual(cp.execFileSync('unzip', ['-Z1', zip], {encoding:'utf8'}).trim().split('\n').sort(),
      ['eme-rest-api/README.md','eme-rest-api/eme-rest-api.php','eme-rest-api/readme.txt']);
  } finally {
    fs.rmSync(root, {recursive:true, force:true});
  }
});
