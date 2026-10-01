import json
from src.buffers.spool import EventSpool


def test_spool_roundtrip_and_corrupt_quarantine(tmp_path):
    p = str(tmp_path / "events.jsonl")
    s = EventSpool(p)
    assert s.append([{"a": 1}, {"b": 2}]) == 2
    with open(p, "a") as f:
        f.write("NOT JSON{{{\n")
    out = s.drain(limit=10)
    assert out == [{"a": 1}, {"b": 2}]
    # corrupt line quarantined (dropped), spool empty afterwards
    assert s.drain() == []


def test_spool_drain_limit(tmp_path):
    p = str(tmp_path / "e.jsonl")
    s = EventSpool(p)
    s.append([{"i": i} for i in range(5)])
    assert len(s.drain(limit=2)) == 2
    assert len(s.drain(limit=10)) == 3
