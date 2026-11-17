# File-only performance

Measured 2026-11-17 on the maintainer's macOS arm64 workstation, PHP 8.4.5,
Laravel 13.42.0. Full machine load and filesystem cache are uncontrolled; these
are local observations, not latency guarantees. Reproduce with
`php tools/benchmark.php /absolute/smoke-host`. Raw results are in
[benchmark-results.json](benchmark-results.json).

| Synthetic entries | File size | First 50, median | No-match window, median | Bytes read for no-match | Peak PHP memory |
|---|---:|---:|---:|---:|---:|
| 10K | 2.96 MB | 0.192 ms | 27.437 ms | 2,965,445 | 20 MiB |
| 100K | 29.6 MB | 0.203 ms | 80.185 ms | 8,388,608 | 20 MiB |
| 1M | 296 MB | 0.228 ms | 80.168 ms | 8,388,608 | 20 MiB |

Mean formatter cost over 10K calls: 2.825 microseconds/record. Mean formatting +
private-file open + exclusive lock + append + flush + close: 40.540 microseconds.
This measures end-to-end package writing in a warm local process, not a difference
against an application's existing production logging pipeline. `fflush` is not
`fsync`: no crash-durability guarantee is claimed. Directory/file checks are
included in the write timing, dataset generation is outside read timing.

Method: stream identical synthetic, schema-valid 296-byte JSON lines, median of
5 first-page searches and 5 literal no-match searches per dataset. No full dataset
is loaded into a PHP array. Memory includes a booted Laravel host; peak remains
20 MiB for this run. Filesystem cache is warm, no cold-disk/network benchmark.
The 1M reader fixture intentionally exceeds the writer's default 64 MiB/day cap:
external oversized files must remain bounded when read. It does not imply the
writer ignores its cap. Byte counts include bounded line-boundary overlap reads.

The reader scans backwards in 64 KiB blocks (or forwards for oldest order),
processes complete bounded lines and stops at result, file, byte or cooperative
time quotas. Default search covers at most 14 configured daily files and 8 MiB /
250 ms per page. A no-match search on a large archive needs more windows; there
is no global total or full-archive instant index. Appends do not shift snapshot
pagination; removed/replaced/truncated files are skipped with rotation feedback.
Malformed entries and partial/oversized fragments are counted and skipped.

A 64 MiB daily write cap favors predictable disk use over completeness under a
log flood: rejected records generate a constant-content stderr warning. Raise
limits deliberately after measuring real traffic, shorten retention, or bind a
separate LogSource backed by a production log service if file-only search no
longer fits. Never add an unbounded scan merely to calculate a total count.
