"""Advanced collector coverage."""
from src.collector.advanced import journald_available, process_snapshot


def test_process_snapshot_shape():
    # Must never crash, even in containers without /proc cmdlines.
    res = process_snapshot("h", limit=5)
    assert isinstance(res, list)


def test_journald_probe_bool():
    assert isinstance(journald_available(), bool)
