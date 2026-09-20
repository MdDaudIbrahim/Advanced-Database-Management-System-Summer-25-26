-- =====================================================================
-- Sports Tournament Management System (STMS)
-- Master Database Schema, Sequences, Constraints, Triggers & Sample Data
-- American International University-Bangladesh (AIUB)
-- Course: Advanced Database Management System (ADBMS) — Final Term
-- Target DBMS: Oracle Database 10g Express Edition (XE) / 11g / 19c / 21c
-- File: database/schema.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. DROP EXISTING OBJECTS (Run safely in clean order)
-- ---------------------------------------------------------------------
BEGIN
    FOR t IN (SELECT table_name FROM user_tables WHERE table_name IN (
        'PAYMENT', 'TICKET', 'SPECTATOR', 'MATCHES', 'VENUE', 'REGISTRATION', 
        'PLAYER_PHONE', 'PLAYER', 'TEAM', 'COACH_PHONE', 'COACH', 'STAFF', 'TOURNAMENT'
    )) LOOP
        EXECUTE IMMEDIATE 'DROP TABLE ' || t.table_name || ' CASCADE CONSTRAINTS';
    END LOOP;
END;
/

BEGIN
    FOR s IN (SELECT sequence_name FROM user_sequences WHERE sequence_name IN (
        'SEQ_COACH', 'SEQ_TEAM', 'SEQ_PLAYER', 'SEQ_TOURNAMENT',
        'SEQ_REGISTRATION', 'SEQ_VENUE', 'SEQ_MATCHES', 'SEQ_SPECTATOR', 
        'SEQ_TICKET', 'SEQ_STAFF', 'SEQ_PAYMENT'
    )) LOOP
        EXECUTE IMMEDIATE 'DROP SEQUENCE ' || s.sequence_name;
    END LOOP;
END;
/

-- ---------------------------------------------------------------------
-- 2. CREATE SEQUENCES FOR AUTOMATIC PRIMARY KEYS
-- ---------------------------------------------------------------------
CREATE SEQUENCE seq_coach        START WITH 101 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_team         START WITH 201 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_player       START WITH 301 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_tournament   START WITH 401 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_registration START WITH 501 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_venue        START WITH 601 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_matches      START WITH 701 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_spectator    START WITH 801 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_ticket       START WITH 901 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_staff        START WITH 501 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE seq_payment      START WITH 1001 INCREMENT BY 1 NOCACHE;

-- ---------------------------------------------------------------------
-- 3. CREATE NORMALIZED TABLES (3NF)
-- ---------------------------------------------------------------------

-- COACH Table (System User & Team Lead)
CREATE TABLE COACH (
    CoachID        INT PRIMARY KEY,
    C_Name         VARCHAR2(100) NOT NULL,
    Specialization VARCHAR2(50)  NOT NULL,
    Email          VARCHAR2(100) UNIQUE NOT NULL,
    Salary         NUMBER(10,2)  CHECK (Salary > 0),
    Password       VARCHAR2(100) DEFAULT 'coach123'
);

-- Multivalued phone numbers for COACH (1NF/3NF)
CREATE TABLE COACH_PHONE (
    CoachID        INT,
    Phone          VARCHAR2(20),
    PRIMARY KEY (CoachID, Phone),
    FOREIGN KEY (CoachID) REFERENCES COACH(CoachID) ON DELETE CASCADE
);

-- STAFF Table (System User: Data Entry Staff)
CREATE TABLE STAFF (
    StaffID        INT PRIMARY KEY,
    S_Name         VARCHAR2(100) NOT NULL,
    Email          VARCHAR2(100) UNIQUE NOT NULL,
    Phone          VARCHAR2(20),
    Password       VARCHAR2(100) DEFAULT 'staff123',
    Role           VARCHAR2(50)  DEFAULT 'Data Entry Staff',
    CreatedAt      DATE          DEFAULT SYSDATE
);

-- TOURNAMENT Table
CREATE TABLE TOURNAMENT (
    TournamentID   INT PRIMARY KEY,
    T_Name         VARCHAR2(120) NOT NULL,
    StartDate      DATE NOT NULL,
    EndDate        DATE NOT NULL,
    PrizeMoney     NUMBER(12,2) CHECK (PrizeMoney >= 0),
    Sport_Type     VARCHAR2(50) DEFAULT 'Football',
    Location       VARCHAR2(100) DEFAULT 'Main Campus',
    Max_Teams      INT DEFAULT 8,
    CHECK (EndDate >= StartDate)
);

-- TEAM Table
CREATE TABLE TEAM (
    TeamID         INT PRIMARY KEY,
    TeamName       VARCHAR2(100) NOT NULL,
    Category       VARCHAR2(50),
    Sport_Type     VARCHAR2(50) DEFAULT 'Football',
    HomeCity       VARCHAR2(50),
    RegDate        DATE DEFAULT SYSDATE,
    CoachID        INT NOT NULL,
    TournamentID   INT,
    FOREIGN KEY (CoachID) REFERENCES COACH(CoachID) ON DELETE CASCADE,
    FOREIGN KEY (TournamentID) REFERENCES TOURNAMENT(TournamentID) ON DELETE SET NULL
);

-- PLAYER Table
CREATE TABLE PLAYER (
    PlayerID       INT PRIMARY KEY,
    P_Name         VARCHAR2(100) NOT NULL,
    Position       VARCHAR2(50),
    JerseyNo       INT CHECK (JerseyNo BETWEEN 1 AND 99),
    Jersey_No      INT,
    DOB            DATE,
    Height         NUMBER(5,2),
    Weight         NUMBER(5,2),
    TeamID         INT NOT NULL,
    FOREIGN KEY (TeamID) REFERENCES TEAM(TeamID) ON DELETE CASCADE
);

-- Multivalued phone numbers for PLAYER (1NF/3NF)
CREATE TABLE PLAYER_PHONE (
    PlayerID       INT,
    Phone          VARCHAR2(20),
    PRIMARY KEY (PlayerID, Phone),
    FOREIGN KEY (PlayerID) REFERENCES PLAYER(PlayerID) ON DELETE CASCADE
);

-- REGISTRATION Table (Bridge between TEAM and TOURNAMENT)
CREATE TABLE REGISTRATION (
    RegistrationID INT PRIMARY KEY,
    RegDate        DATE DEFAULT SYSDATE,
    RegFee         NUMBER(10,2) CHECK (RegFee >= 0),
    TeamID         INT NOT NULL,
    TournamentID   INT NOT NULL,
    FOREIGN KEY (TeamID) REFERENCES TEAM(TeamID) ON DELETE CASCADE,
    FOREIGN KEY (TournamentID) REFERENCES TOURNAMENT(TournamentID) ON DELETE CASCADE,
    CONSTRAINT uq_team_tournament UNIQUE (TeamID, TournamentID)
);

-- VENUE Table
CREATE TABLE VENUE (
    VenueID        INT PRIMARY KEY,
    V_Name         VARCHAR2(100) NOT NULL,
    Location       VARCHAR2(100) NOT NULL,
    Capacity       INT CHECK (Capacity > 0),
    ContactNo      VARCHAR2(30)
);

-- MATCHES Table
CREATE TABLE MATCHES (
    MatchID        INT PRIMARY KEY,
    MatchDate      DATE NOT NULL,
    MatchTime      VARCHAR2(20) NOT NULL,
    MatchStatus    VARCHAR2(30) DEFAULT 'Scheduled',
    Result         VARCHAR2(100),
    HomeScore      INT DEFAULT 0,
    AwayScore      INT DEFAULT 0,
    TournamentID   INT NOT NULL,
    VenueID        INT NOT NULL,
    HomeTeamID     INT NOT NULL,
    AwayTeamID     INT NOT NULL,
    FOREIGN KEY (TournamentID) REFERENCES TOURNAMENT(TournamentID) ON DELETE CASCADE,
    FOREIGN KEY (VenueID)      REFERENCES VENUE(VenueID),
    FOREIGN KEY (HomeTeamID)   REFERENCES TEAM(TeamID),
    FOREIGN KEY (AwayTeamID)   REFERENCES TEAM(TeamID),
    CHECK (HomeTeamID <> AwayTeamID)
);

-- SPECTATOR Table
CREATE TABLE SPECTATOR (
    SpectatorID    INT PRIMARY KEY,
    S_Name         VARCHAR2(100) NOT NULL,
    Email          VARCHAR2(100) UNIQUE NOT NULL,
    Phone          VARCHAR2(20)
);

-- TICKET Table
CREATE TABLE TICKET (
    TicketID       INT PRIMARY KEY,
    MatchID        INT NOT NULL,
    SpectatorID    INT NOT NULL,
    SeatNo         INT CHECK (SeatNo > 0),
    Ticket_Type    VARCHAR2(40) DEFAULT 'Standard',
    Price          NUMBER(8,2) CHECK (Price >= 0),
    Payment_Status VARCHAR2(30) DEFAULT 'Pending',
    Pay_Method     VARCHAR2(40) DEFAULT 'Cash',
    FOREIGN KEY (MatchID)     REFERENCES MATCHES(MatchID) ON DELETE CASCADE,
    FOREIGN KEY (SpectatorID) REFERENCES SPECTATOR(SpectatorID) ON DELETE CASCADE,
    CONSTRAINT uq_match_seat UNIQUE (MatchID, SeatNo)
);

-- PAYMENT Table (Financial Ledger)
CREATE TABLE PAYMENT (
    PaymentID      INT PRIMARY KEY,
    TicketID       INT NOT NULL,
    Amount         NUMBER(10,2) NOT NULL,
    PaymentDate    DATE DEFAULT SYSDATE,
    PaymentMethod  VARCHAR2(50) DEFAULT 'Cash',
    PaymentStatus  VARCHAR2(30) DEFAULT 'Success',
    FOREIGN KEY (TicketID) REFERENCES TICKET(TicketID) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 4. TRIGGERS FOR AUTO-INCREMENT PRIMARY KEYS (Oracle Standard)
-- ---------------------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_coach_id
BEFORE INSERT ON COACH FOR EACH ROW
BEGIN
    IF :NEW.CoachID IS NULL THEN
        SELECT seq_coach.NEXTVAL INTO :NEW.CoachID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_team_id
BEFORE INSERT ON TEAM FOR EACH ROW
BEGIN
    IF :NEW.TeamID IS NULL THEN
        SELECT seq_team.NEXTVAL INTO :NEW.TeamID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_player_id
BEFORE INSERT ON PLAYER FOR EACH ROW
BEGIN
    IF :NEW.PlayerID IS NULL THEN
        SELECT seq_player.NEXTVAL INTO :NEW.PlayerID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_tournament_id
BEFORE INSERT ON TOURNAMENT FOR EACH ROW
BEGIN
    IF :NEW.TournamentID IS NULL THEN
        SELECT seq_tournament.NEXTVAL INTO :NEW.TournamentID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_registration_id
BEFORE INSERT ON REGISTRATION FOR EACH ROW
BEGIN
    IF :NEW.RegistrationID IS NULL THEN
        SELECT seq_registration.NEXTVAL INTO :NEW.RegistrationID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_venue_id
BEFORE INSERT ON VENUE FOR EACH ROW
BEGIN
    IF :NEW.VenueID IS NULL THEN
        SELECT seq_venue.NEXTVAL INTO :NEW.VenueID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_matches_id
BEFORE INSERT ON MATCHES FOR EACH ROW
BEGIN
    IF :NEW.MatchID IS NULL THEN
        SELECT seq_matches.NEXTVAL INTO :NEW.MatchID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_spectator_id
BEFORE INSERT ON SPECTATOR FOR EACH ROW
BEGIN
    IF :NEW.SpectatorID IS NULL THEN
        SELECT seq_spectator.NEXTVAL INTO :NEW.SpectatorID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_ticket_id
BEFORE INSERT ON TICKET FOR EACH ROW
BEGIN
    IF :NEW.TicketID IS NULL THEN
        SELECT seq_ticket.NEXTVAL INTO :NEW.TicketID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_staff_id
BEFORE INSERT ON STAFF FOR EACH ROW
BEGIN
    IF :NEW.StaffID IS NULL THEN
        SELECT seq_staff.NEXTVAL INTO :NEW.StaffID FROM DUAL;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_payment_id
BEFORE INSERT ON PAYMENT FOR EACH ROW
BEGIN
    IF :NEW.PaymentID IS NULL THEN
        SELECT seq_payment.NEXTVAL INTO :NEW.PaymentID FROM DUAL;
    END IF;
END;
/

-- ---------------------------------------------------------------------
-- 5. INSERT REALISTIC SAMPLE DATA
-- ---------------------------------------------------------------------

-- COACHES (Credentials managed by Admin)
INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary, Password)
VALUES (seq_coach.NEXTVAL, 'Kamal Hossain', 'Football', 'kamal.h@gmail.com', 45000, 'coach123');

INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary, Password)
VALUES (seq_coach.NEXTVAL, 'Rashed Karim', 'Cricket', 'rashed.k@yahoo.com', 52000, 'coach123');

INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary, Password)
VALUES (seq_coach.NEXTVAL, 'Tanvir Ahmed', 'Football', 'tanvir.a@gmail.com', 48000, 'coach123');

INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary, Password)
VALUES (seq_coach.NEXTVAL, 'Mahmudul Hasan', 'Basketball', 'mahmud.h@gmail.com', 40000, 'coach123');

INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary, Password)
VALUES (seq_coach.NEXTVAL, 'Faisal Chowdhury', 'Cricket', 'faisal.c@gmail.com', 50000, 'coach123');

-- COACH PHONES
INSERT INTO COACH_PHONE VALUES (101, '01711122233');
INSERT INTO COACH_PHONE VALUES (101, '01911122233');
INSERT INTO COACH_PHONE VALUES (102, '01822233344');
INSERT INTO COACH_PHONE VALUES (103, '01733344455');
INSERT INTO COACH_PHONE VALUES (104, '01644455566');
INSERT INTO COACH_PHONE VALUES (105, '01555566677');

-- DATA ENTRY STAFF
INSERT INTO STAFF (StaffID, S_Name, Email, Phone, Password, Role)
VALUES (seq_staff.NEXTVAL, 'Rahat Karim', 'rahat.k@gmail.com', '01712345678', 'staff123', 'Data Entry Staff');

-- TOURNAMENTS
INSERT INTO TOURNAMENT VALUES (seq_tournament.NEXTVAL, 'Inter-University Cricket League 2024', TO_DATE('2026-09-20','YYYY-MM-DD'), TO_DATE('2026-10-30','YYYY-MM-DD'), 150000, 'Cricket', 'Central Stadium', 8);
INSERT INTO TOURNAMENT VALUES (seq_tournament.NEXTVAL, 'Premier Football Cup 2026', TO_DATE('2026-10-01','YYYY-MM-DD'), TO_DATE('2026-11-15','YYYY-MM-DD'), 200000, 'Football', 'Main Campus Field', 12);
INSERT INTO TOURNAMENT VALUES (seq_tournament.NEXTVAL, 'National Inter-College Basketball Meet', TO_DATE('2026-11-01','YYYY-MM-DD'), TO_DATE('2026-11-20','YYYY-MM-DD'), 100000, 'Basketball', 'Indoor Gymnasium', 6);
INSERT INTO TOURNAMENT VALUES (seq_tournament.NEXTVAL, 'Summer Badminton Championship', TO_DATE('2026-08-01','YYYY-MM-DD'), TO_DATE('2026-08-15','YYYY-MM-DD'), 50000, 'Badminton', 'Sports Hall B', 8);
INSERT INTO TOURNAMENT VALUES (seq_tournament.NEXTVAL, 'JAPAN VS USA', TO_DATE('2026-09-29','YYYY-MM-DD'), TO_DATE('2026-09-30','YYYY-MM-DD'), 25000, 'Football', 'USA', 11);

-- TEAMS
INSERT INTO TEAM VALUES (seq_team.NEXTVAL, 'Dhaka Gladiators', 'Cricket', 'Cricket', 'Dhaka', TO_DATE('2026-08-10','YYYY-MM-DD'), 102, 401);
INSERT INTO TEAM VALUES (seq_team.NEXTVAL, 'Chittagong Titans', 'Cricket', 'Cricket', 'Chittagong', TO_DATE('2026-08-12','YYYY-MM-DD'), 105, 401);
INSERT INTO TEAM VALUES (seq_team.NEXTVAL, 'Sylhet Thunder', 'Football', 'Football', 'Sylhet', TO_DATE('2026-08-15','YYYY-MM-DD'), 101, 402);
INSERT INTO TEAM VALUES (seq_team.NEXTVAL, 'Rajshahi Kings', 'Football', 'Football', 'Rajshahi', TO_DATE('2026-08-18','YYYY-MM-DD'), 103, 402);
INSERT INTO TEAM VALUES (seq_team.NEXTVAL, 'Barisal Warriors', 'Basketball', 'Basketball', 'Barisal', TO_DATE('2026-08-20','YYYY-MM-DD'), 104, 403);
INSERT INTO TEAM VALUES (seq_team.NEXTVAL, 'Khulna Tigers', 'Cricket', 'Cricket', 'Khulna', TO_DATE('2026-08-22','YYYY-MM-DD'), 102, 401);

-- REGISTRATIONS
INSERT INTO REGISTRATION VALUES (seq_registration.NEXTVAL, TO_DATE('2026-08-11','YYYY-MM-DD'), 15000, 201, 401);
INSERT INTO REGISTRATION VALUES (seq_registration.NEXTVAL, TO_DATE('2026-08-13','YYYY-MM-DD'), 15000, 202, 401);
INSERT INTO REGISTRATION VALUES (seq_registration.NEXTVAL, TO_DATE('2026-08-16','YYYY-MM-DD'), 20000, 203, 402);
INSERT INTO REGISTRATION VALUES (seq_registration.NEXTVAL, TO_DATE('2026-08-19','YYYY-MM-DD'), 20000, 204, 402);
INSERT INTO REGISTRATION VALUES (seq_registration.NEXTVAL, TO_DATE('2026-08-21','YYYY-MM-DD'), 12000, 205, 403);
INSERT INTO REGISTRATION VALUES (seq_registration.NEXTVAL, TO_DATE('2026-08-23','YYYY-MM-DD'), 15000, 206, 401);

-- PLAYERS
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Shakib Chowdhury', 'All-Rounder', 75, 75, TO_DATE('1998-03-24','YYYY-MM-DD'), 178, 72, 201);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Tamim Alom', 'Opening Batsman', 28, 28, TO_DATE('1997-03-20','YYYY-MM-DD'), 175, 76, 201);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Mushfiqur Rahman', 'Wicketkeeper', 15, 15, TO_DATE('1999-05-09','YYYY-MM-DD'), 165, 65, 201);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Taskin Hossain', 'Fast Bowler', 3, 3, TO_DATE('1999-04-03','YYYY-MM-DD'), 188, 80, 201);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Mehidy Hasan', 'Spin Bowler', 53, 53, TO_DATE('2000-10-25','YYYY-MM-DD'), 174, 68, 201);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Mahmudullah Riyad', 'Middle Order Batsman', 30, 30, TO_DATE('1996-02-04','YYYY-MM-DD'), 180, 74, 202);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Mustafizur Rahman', 'Fast Bowler', 90, 90, TO_DATE('1998-09-06','YYYY-MM-DD'), 182, 70, 202);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Litton Das', 'Wicketkeeper', 16, 16, TO_DATE('1998-10-13','YYYY-MM-DD'), 170, 66, 202);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Jamal Bhuyan', 'Midfielder', 6, 6, TO_DATE('1995-04-10','YYYY-MM-DD'), 176, 70, 203);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Anisur Rahman Zico', 'Goalkeeper', 1, 1, TO_DATE('1997-08-10','YYYY-MM-DD'), 184, 78, 203);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Topu Barman', 'Defender', 4, 4, TO_DATE('1996-12-20','YYYY-MM-DD'), 180, 75, 203);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Rakib Hossain', 'Forward', 10, 10, TO_DATE('2001-11-18','YYYY-MM-DD'), 172, 68, 204);

INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID)
VALUES (seq_player.NEXTVAL, 'Sohel Rana', 'Midfielder', 8, 8, TO_DATE('1998-03-27','YYYY-MM-DD'), 174, 71, 204);

-- PLAYER PHONES
INSERT INTO PLAYER_PHONE VALUES (301, '01710001111');
INSERT INTO PLAYER_PHONE VALUES (302, '01810002222');
INSERT INTO PLAYER_PHONE VALUES (303, '01910003333');
INSERT INTO PLAYER_PHONE VALUES (304, '01610004444');
INSERT INTO PLAYER_PHONE VALUES (305, '01510005555');

-- VENUES
INSERT INTO VENUE VALUES (seq_venue.NEXTVAL, 'Central University Stadium', 'Dhaka Main Campus', 5000, '+8801700000001');
INSERT INTO VENUE VALUES (seq_venue.NEXTVAL, 'North Football Arena', 'Kuril Campus Ground', 3000, '+8801700000002');
INSERT INTO VENUE VALUES (seq_venue.NEXTVAL, 'Mirpur Indoor Complex', 'Mirpur Sector 2', 1500, '+8801700000003');
INSERT INTO VENUE VALUES (seq_venue.NEXTVAL, 'Bir Shreshtha Shaheed Ground', 'Dhanmondi', 4000, '+8801700000004');

-- MATCHES
INSERT INTO MATCHES (MatchID, MatchDate, MatchTime, MatchStatus, Result, HomeScore, AwayScore, TournamentID, VenueID, HomeTeamID, AwayTeamID)
VALUES (seq_matches.NEXTVAL, TO_DATE('2026-09-24','YYYY-MM-DD'), '02:30 PM', 'Scheduled', NULL, 0, 0, 401, 601, 201, 202);

INSERT INTO MATCHES (MatchID, MatchDate, MatchTime, MatchStatus, Result, HomeScore, AwayScore, TournamentID, VenueID, HomeTeamID, AwayTeamID)
VALUES (seq_matches.NEXTVAL, TO_DATE('2026-09-28','YYYY-MM-DD'), '04:00 PM', 'Scheduled', NULL, 0, 0, 401, 601, 201, 206);

INSERT INTO MATCHES (MatchID, MatchDate, MatchTime, MatchStatus, Result, HomeScore, AwayScore, TournamentID, VenueID, HomeTeamID, AwayTeamID)
VALUES (seq_matches.NEXTVAL, TO_DATE('2026-10-05','YYYY-MM-DD'), '03:30 PM', 'Scheduled', NULL, 0, 0, 402, 602, 203, 204);

INSERT INTO MATCHES (MatchID, MatchDate, MatchTime, MatchStatus, Result, HomeScore, AwayScore, TournamentID, VenueID, HomeTeamID, AwayTeamID)
VALUES (seq_matches.NEXTVAL, TO_DATE('2026-10-12','YYYY-MM-DD'), '05:00 PM', 'Scheduled', NULL, 0, 0, 403, 603, 205, 201);

INSERT INTO MATCHES (MatchID, MatchDate, MatchTime, MatchStatus, Result, HomeScore, AwayScore, TournamentID, VenueID, HomeTeamID, AwayTeamID)
VALUES (seq_matches.NEXTVAL, TO_DATE('2026-08-10','YYYY-MM-DD'), '03:00 PM', 'Completed', 'Dhaka Gladiators won by 5 wickets', 185, 180, 401, 601, 201, 202);

-- SPECTATORS
INSERT INTO SPECTATOR VALUES (seq_spectator.NEXTVAL, 'Daud Ibrahim', 'customer@example.com', '01700000011');
INSERT INTO SPECTATOR VALUES (seq_spectator.NEXTVAL, 'Siam Ahmed', 'siam.a@gmail.com', '01800000022');
INSERT INTO SPECTATOR VALUES (seq_spectator.NEXTVAL, 'Nafisa Jahan', 'nafisa.j@gmail.com', '01900000033');
INSERT INTO SPECTATOR VALUES (seq_spectator.NEXTVAL, 'Tanvir Hasan', 'tanvir.h@gmail.com', '01600000044');

-- TICKETS
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
VALUES (seq_ticket.NEXTVAL, 701, 801, 14, 'VIP',      2400.00, 'Paid',    'SSLCommerz');

INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
VALUES (seq_ticket.NEXTVAL, 701, 802, 15, 'VIP',      2400.00, 'Paid',    'bKash');

INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
VALUES (seq_ticket.NEXTVAL, 701, 803, 22, 'Standard',  200.00, 'Pending', 'Cash');

INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
VALUES (seq_ticket.NEXTVAL, 702, 801,  8, 'Premium',   500.00, 'Paid',    'Visa Card');

INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
VALUES (seq_ticket.NEXTVAL, 702, 804, 30, 'Standard',  200.00, 'Pending', 'Cash');

INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method)
VALUES (seq_ticket.NEXTVAL, 703, 802, 12, 'Standard',  250.00, 'Paid',    'Nagad');

-- PAYMENTS (Associated with Paid Tickets)
INSERT INTO PAYMENT (PaymentID, TicketID, Amount, PaymentDate, PaymentMethod, PaymentStatus)
VALUES (seq_payment.NEXTVAL, 901, 2400.00, SYSDATE - 5, 'SSLCommerz', 'Success');

INSERT INTO PAYMENT (PaymentID, TicketID, Amount, PaymentDate, PaymentMethod, PaymentStatus)
VALUES (seq_payment.NEXTVAL, 902, 2400.00, SYSDATE - 4, 'bKash', 'Success');

INSERT INTO PAYMENT (PaymentID, TicketID, Amount, PaymentDate, PaymentMethod, PaymentStatus)
VALUES (seq_payment.NEXTVAL, 904, 500.00, SYSDATE - 2, 'Visa Card', 'Success');

INSERT INTO PAYMENT (PaymentID, TicketID, Amount, PaymentDate, PaymentMethod, PaymentStatus)
VALUES (seq_payment.NEXTVAL, 906, 250.00, SYSDATE - 1, 'Nagad', 'Success');

COMMIT;

-- ---------------------------------------------------------------------
-- 6. USERS, ROLES, AND PRIVILEGES (Oracle Security Requirements)
-- ---------------------------------------------------------------------
-- Note: Execute the following block as SYSTEM / SYSDBA in Oracle SQL*Plus:
/*
-- 1. Create Dedicated Oracle Users
CREATE USER tournament_admin IDENTIFIED BY admin123;
GRANT CONNECT, RESOURCE, DBA TO tournament_admin;

CREATE USER entry_staff IDENTIFIED BY staff123;
GRANT CONNECT, RESOURCE TO entry_staff;

-- 2. Define Custom Role for Data Entry Staff
CREATE ROLE data_entry_role;
GRANT SELECT, INSERT, UPDATE ON PLAYER TO data_entry_role;
GRANT SELECT, INSERT, UPDATE ON SPECTATOR TO data_entry_role;
GRANT SELECT, INSERT, UPDATE ON TICKET TO data_entry_role;
GRANT SELECT, INSERT, UPDATE ON PAYMENT TO data_entry_role;
GRANT SELECT ON MATCHES TO data_entry_role;
GRANT SELECT ON TEAM TO data_entry_role;
GRANT SELECT ON VENUE TO data_entry_role;
GRANT data_entry_role TO entry_staff;

-- 3. Define Custom Role for Report Viewers / Auditors
CREATE ROLE report_viewer_role;
GRANT SELECT ON MATCHES TO report_viewer_role;
GRANT SELECT ON TICKET TO report_viewer_role;
GRANT SELECT ON PAYMENT TO report_viewer_role;
GRANT SELECT ON TEAM TO report_viewer_role;
GRANT SELECT ON PLAYER TO report_viewer_role;
GRANT SELECT ON TOURNAMENT TO report_viewer_role;
*/
