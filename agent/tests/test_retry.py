from unittest.mock import MagicMock, patch
from src.client import CipherSocClient
from src.config import AgentConfig


def _cfg(**kw):
    base = dict(server_url="http://x/api/v1", enrollment_token="t", hostname="h")
    base.update(kw)
    return AgentConfig(**base)


def test_send_events_retries_then_succeeds(tmp_path):
    cfg = _cfg(state_path=str(tmp_path / "a.json"))
    c = CipherSocClient(cfg)
    c.identity = MagicMock(agent_id="a", api_token="t")
    ok = MagicMock(status_code=200)
    ok.json.return_value = {"success": True, "data": {"accepted": 1}}
    with patch.object(c.session, "post", side_effect=[MagicMock(status_code=500), ok]) as m, patch("src.client.time.sleep", return_value=None):
        accepted, _ = c.send_events([{"message": "hi"}])
        assert accepted == 1
        assert m.call_count == 2


def test_auth_failure_not_retried(tmp_path):
    cfg = _cfg(state_path=str(tmp_path / "a.json"))
    c = CipherSocClient(cfg)
    c.identity = MagicMock(agent_id="a", api_token="t")
    with patch.object(c.session, "post", return_value=MagicMock(status_code=401, text="nope")) as m:
        try:
            c.send_events([{"message": "hi"}])
            assert False, "should raise"
        except PermissionError:
            pass
        assert m.call_count == 1
