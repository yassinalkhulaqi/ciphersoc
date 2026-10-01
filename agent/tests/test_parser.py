from src.parser.normalize import structure_line


def test_ssh_fail_structured():
    e = structure_line("Nov 12 10:11:12 h sshd[1]: Failed password for root from 10.0.0.1 port 22 ssh2", "h")
    assert e["event_type"] == "authentication_failure"
    assert e["source_ip"] == "10.0.0.1"
    assert e["username"] == "root"


def test_ssh_ok_structured():
    e = structure_line("Nov 12 h sshd[1]: Accepted password for alice from 10.0.0.2 port 22", "h")
    assert e["event_type"] == "authentication_success"
    assert e["status"] == "success"


def test_generic_line_defaults():
    e = structure_line("some random kernel line", "h")
    assert e["event_type"] == "system"
