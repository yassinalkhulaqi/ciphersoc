from src.config import AgentConfig


def test_config_from_env(monkeypatch):
    monkeypatch.setenv("CIPHERSOC_URL", "https://soc/api/v1")
    monkeypatch.setenv("CIPHERSOC_ENROLLMENT_TOKEN", "tok")
    cfg = AgentConfig.from_env_and_args([])
    assert cfg.server_url == "https://soc/api/v1"
    assert cfg.verify_tls is True
    cfg2 = AgentConfig.from_env_and_args(["--insecure"])
    assert cfg2.verify_tls is False
