"""FIM + metrics coverage."""
from src.collector.fim import check_paths
from src.collector.system_metrics import collect_metrics


def test_fim_baseline_then_detect(tmp_path):
    f = tmp_path / "watch.txt"
    f.write_text("v1")
    state = str(tmp_path / "fim.json")
    assert check_paths([str(f)], state) == []
    f.write_text("v2")
    evts = check_paths([str(f)], state)
    assert len(evts) == 1
    assert evts[0]["event_type"] == "file_modified"


def test_metrics_shape():
    m = collect_metrics("test-host")
    assert m is not None
    assert m["event_type"] == "host_metrics"
    assert m["hostname"] == "test-host"
