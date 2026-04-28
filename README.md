# Zenith 랜딩 운영 모듈

Zenith 랜딩 운영 모듈은 광고/이벤트 캠페인용 랜딩 페이지를 여러 도메인에서 운영하고, 방문자 요청을 수집한 뒤 중복 검증, 상태 분류, 외부 제휴 전송까지 처리하는 PHP 기반 운영 코드입니다.

이 문서는 원본 운영 모듈 전체가 아니라, 핵심 처리 파일을 기준으로 정리한 소개 문서입니다. 원래 구조는 이벤트 번호 기반 URL 라우팅, 랜딩 페이지 출력, 신청 데이터 저장, 중복/블랙리스트/IP 검증, 완료 페이지 이동, Facebook Pixel 전송, 제휴사 interlock 재전송까지 포함하는 실무형 PHP 모듈입니다.

## 프로젝트 개요

- 이벤트 번호 또는 해시 기반 랜딩 페이지 라우팅
- 랜딩별 HTML/PHP 템플릿 동적 로드
- 신청 폼 데이터 수집 및 저장
- 이름/전화번호/IP/쿠키 기준 중복 검증
- 블랙리스트 및 운영 정책 기반 상태 분류
- 외부 제휴사 interlock 전송 및 재전송
- 완료 페이지 이동 및 Pixel 이벤트 전송

## 이 저장소가 맡는 역할

Zenith 운영 구조는 대체로 다음과 같이 나뉩니다.

- 운영/관리 계층: 이벤트, 광고주, 매체, 전송 조건 관리
- 랜딩 처리 계층: 방문 유입, 랜딩 출력, 신청 저장, interlock 전송
- 제휴/외부 연동 계층: 광고 플랫폼, CRM, 수집 API 등 외부 시스템

이 저장소는 두 번째 계층에 해당합니다. 운영 DB에 등록된 이벤트 정보를 읽어 실제 방문자 요청을 처리하고, 조건에 따라 외부 시스템으로 신청 정보를 전달하는 실행 모듈입니다.

## 핵심 처리 흐름

1. 방문자가 이벤트 URL 또는 해시 URL로 랜딩 페이지에 접근합니다.
2. `zenith_core.php`가 이벤트 번호를 해석하고 DB에서 랜딩 정보를 조회합니다.
3. 이벤트별 랜딩 파일과 헤더/푸터 템플릿을 조합해 페이지를 출력합니다.
4. 사용자가 폼을 제출하면 `zenith_check_proc.php`가 필수값, 중복, 블랙리스트, 정책 조건을 검사합니다.
5. 정상 데이터는 DB에 저장되고 상태 코드가 분류됩니다.
6. 이벤트 설정에 따라 Facebook Pixel 또는 제휴사 interlock 전송이 수행됩니다.
7. 처리 결과에 따라 `thanks` 페이지나 결과 페이지로 이동합니다.

## 주요 기능

### 1. 랜딩 페이지 라우팅 및 출력

- 이벤트 번호 또는 해시 기반 URL 처리
- 이벤트별 `v_{번호}.php`, `v_{번호}_act.php`, `v_{번호}_thanks.php` 템플릿 로드
- 공통 `inc/head.php`, `inc/tail.php` 조합
- 이벤트별 스크립트, 스타일, 완료 페이지 동적 구성

핵심 진입점:

- `zenith_core.php`

### 2. 신청 데이터 수집 및 검증

- JSON 또는 일반 POST 데이터 처리
- 이름, 전화번호, 성별, 나이, 주소, 메모 등 수집
- 나이 제한 검증
- 이름/전화번호/IP 기준 중복 사전 검증
- 블랙리스트 전화번호 차단
- 쿠키 기반 중복 처리

핵심 파일:

- `zenith_check_proc.php`
- `zenith_cookie.php`
- `zenith_ip.php`

### 3. 상태 분류 및 운영 데이터 후처리

- 정상/중복/연령 조건 불일치/전화번호 불량/테스트 데이터 구분
- 이벤트 리드 상태 업데이트
- 메모 테이블 추가 기록
- AppSubscribe 계열 저장 처리
- 요청 로그 저장

핵심 파일:

- `zenith_check_proc.php`
- `zenith_core.php`

### 4. 외부 제휴 연동과 재전송

- 이벤트별 interlock 스크립트 실행
- 외부 API 응답을 성공/실패 규칙에 따라 정규화
- 전송 실패 메모 기록
- 실패 건 재전송 스크립트 제공

핵심 파일:

- `zenith_interlock.php`
- `interlock_resend.php`
- `interlock_resend_all.php`

### 5. 암호화 및 보안 처리

- 전화번호 등 민감정보 암복호화
- AES 키 기반 데이터 암호화
- 쿠키 식별자 생성
- 내부 IP와 외부 IP 구분 처리

핵심 파일:

- `zenith_encryption.php`
- `zenith_ip.php`
- `zenith_cookie.php`

## 기술 스택

### Backend

![PHP](https://img.shields.io/badge/PHP-Module%20Runtime-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQLi](https://img.shields.io/badge/MySQLi-Database%20Access-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![cURL](https://img.shields.io/badge/cURL-HTTP%20Client-0F172A?style=for-the-badge)
![OpenSSL](https://img.shields.io/badge/OpenSSL-AES%20Encryption-721412?style=for-the-badge)
![Sodium](https://img.shields.io/badge/Sodium-Crypto%20Support-1E3A8A?style=for-the-badge)
![Session](https://img.shields.io/badge/PHP%20Session-Visitor%20State-4B5563?style=for-the-badge)
![Cookie](https://img.shields.io/badge/Cookie-Duplicate%20Control-6B7280?style=for-the-badge)

### Frontend / Asset Handling

빌드 도구 기반 프런트엔드 프로젝트는 아니며, 이벤트별 PHP 템플릿과 정적 HTML/CSS/JS 파일을 직접 로드하는 구조입니다.

![PHP Template](https://img.shields.io/badge/PHP-Template%20Rendering-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Static Assets](https://img.shields.io/badge/Static%20Assets-HTML%20%2F%20CSS%20%2F%20JS-0F172A?style=for-the-badge)

## 파일 구성

| 파일 | 설명 |
| --- | --- |
| `zenith_core.php` | 랜딩 라우팅, 페이지 렌더링, 신청 처리, 완료 페이지 이동 |
| `zenith_check_proc.php` | 신청 데이터 검증, 상태 분류, 저장 후처리 |
| `zenith_interlock.php` | 외부 제휴 연동 결과 기록 및 재전송 처리 |
| `zenith_encryption.php` | 신청 데이터 암복호화 |
| `zenith_ip.php` | 방문자 IP 확인, 내부망/차단 처리 |
| `zenith_cookie.php` | 운영 쿠키 생성 및 조회 |
| `interlock_resend.php` | 특정 실패 건 재전송 |
| `interlock_resend_all.php` | 조건 기반 일괄 재전송 |
| `zenith_test.php` | 연동/요청 테스트 스크립트 |
| `zenith_test_form.php` | 폼 전송 테스트 화면 |

## 원본 모듈 기준 구성

현재 저장소에는 핵심 파일 위주로 발췌되어 있지만, 원본 모듈은 코드 안에서 참조하는 다음 구조를 함께 사용하는 형태입니다.

- `data/` : 이벤트별 랜딩 파일, thank-you 파일, interlock 스크립트
- `inc/` : 공통 head, tail, alert, thanks 템플릿
- `error/` : 오류 페이지
- `../.env` : 암호화 키 설정
- `zenith_db.php` : DB 연결 및 쿼리 래퍼

즉, 지금 저장소는 전체 모듈의 축약본이 아니라 핵심 처리 흐름을 보여주기 위한 발췌본입니다.

## 실행 환경

별도의 Composer/NPM 빌드 단계는 없습니다.

```bash
cp .env.example ../.env
```

`../.env`에는 최소 다음 값이 필요합니다.

| 변수 | 설명 |
| --- | --- |
| `encryption.key` | Sodium 키용 hex 문자열 |
| `aes.key` | AES 암복호화 키 |

필수 서버 조건:

- PHP 실행 가능한 웹 서버
- MySQL 접근 가능 환경
- `mysqli`, `curl`, `openssl`, `mbstring` 확장
- 가능하면 `sodium` 지원 환경
- 세션/쿠키 사용 가능한 HTTPS 환경

## 현재 코드 기준 참고 사항

- 현재 저장소는 원본 모듈 전체가 아니라 주요 처리 파일만 선별한 상태입니다.
- `zenith_core.php`는 `zenith_db.php`에 의존하며, 원본 모듈에서는 함께 동작하는 구조입니다.
- 랜딩 파일은 `data/{광고주명}/v_번호.php` 형식으로 분리되어 있다고 가정합니다.
- `.env`는 현재 프로젝트 루트가 아니라 상위 경로 `../.env`에서 읽습니다.
- 프레임워크 없이 전역 include와 파일 기반 규칙으로 동작하므로, 배포 구조와 디렉터리 배치가 코드 실행에 직접 영향을 줍니다.
- **실제 마케팅/이벤트 랜딩 운영에 투입된 PHP 모듈의 핵심 처리부**

## 보완한 부분

- 깨진 한글 README를 프로젝트 소개용 문서로 재작성
- 실제 코드 구조 기준으로 랜딩 처리 흐름과 역할 정리
- 기술 스택을 뱃지 형식으로 정리
- Composer/NPM이 없는 구조를 문서에 명확히 반영
- 원본 모듈 대비 현재 저장소가 핵심 파일 발췌본이라는 점을 반영

## 개발 정보

- 개발 기간: 1개월
- 개발 인원: 2명
